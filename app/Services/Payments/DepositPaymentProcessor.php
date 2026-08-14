<?php

namespace App\Services\Payments;

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\CheckoutHold;
use App\Models\Deposit;
use App\Models\Owner;
use App\Notifications\Concerns\NotifiesStaff;
use App\Notifications\DepositConfirmedNotification;
use App\Notifications\DepositPaidNotification;
use App\Notifications\DepositUnavailableNotification;
use App\Notifications\NewDepositCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Shared by the webhook (normal path), the daily reconciliation job
 * (catches a webhook that never arrived), and the admin's manual
 * "mark paid" action, so none of these entry points can ever drift apart
 * on what "a Deposit got paid" actually does.
 */
class DepositPaymentProcessor
{
    use NotifiesStaff;

    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Holds the cat (status `pending`) immediately, before any payment is
     * confirmed — used by the admin-recorded reservation flows
     * (Admin\DepositController::store()/assignCat()), which trust a
     * staff-entered reservation the moment it's made. The public flow
     * (Public\DepositController::store()) deliberately does *not* call
     * this — see that method's own docblock — a public visitor's cat
     * isn't held until confirmPaid() below actually confirms payment.
     *
     * $notifyStaff is false when called from
     * Admin\DepositController::assignCat() — that call re-uses reserve()
     * for its cat-status side effect on an *existing* deposit, not a new
     * one, so "Nouvelle réservation" would misrepresent what happened
     * (and duplicate the notification already sent when the deposit
     * itself was created).
     */
    public function reserve(Deposit $deposit, bool $notifyStaff = true): void
    {
        if ($deposit->cat_id !== null) {
            $deposit->cat->setStatus(CatStatus::Pending->value);
        }

        if (! $notifyStaff) {
            return;
        }

        // created_by is only set for a deposit an admin recorded themselves
        // (see Admin\DepositController::store()) — excluded so they don't
        // get notified of their own action.
        Notification::send($this->activeStaff($deposit->created_by), new NewDepositCreatedNotification($deposit));
    }

    /**
     * The atomic re-check that makes manual-capture Stripe payments safe.
     * Deposit::blocksNewReservation() already stops a *second* deposit
     * from being created once a cat's deposit is paid, but with
     * capture_method: manual several visitors can each hold a parallel,
     * still-uncaptured authorization for the same cat before any of them
     * is confirmed — this is the last line of defense, run inside a DB
     * transaction with the target row (and any competing paid one) locked,
     * right before either deposit is actually charged. See CLAUDE.md.
     *
     * $providerReference is null for a manually-recorded payment
     * (cash/bank_transfer/twint_manual) — there's no PSP reference to
     * store, so the existing value (if any) is left untouched, and no
     * gateway capture is attempted (there's nothing authorized on Stripe
     * to capture).
     */
    public function markPaid(Deposit $deposit, ?string $providerReference = null): void
    {
        DB::transaction(function () use ($deposit, $providerReference): void {
            $locked = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === DepositStatus::Paid) {
                return;
            }

            if ($locked->cat_id !== null) {
                $lostRace = Deposit::query()
                    ->where('cat_id', $locked->cat_id)
                    ->whereKeyNot($locked->id)
                    ->where('status', DepositStatus::Paid)
                    ->lockForUpdate()
                    ->exists();

                if ($lostRace) {
                    $this->loseRace($locked);

                    return;
                }
            }

            $this->confirmPaid($locked, $providerReference);
        });
    }

    /**
     * Builds the Deposit itself — the webhook (Public\StripeWebhookController)
     * no longer has one to update, since Public\DepositController::store()
     * stopped creating one up front (see CLAUDE.md). Everything needed
     * rides along as PaymentIntent metadata instead (see CheckoutData /
     * StripeGateway::createPaymentIntent()).
     *
     * Same lock-then-recheck shape as markPaid() above, but there is no
     * existing Deposit row to lock for the cat this PaymentIntent is
     * racing against — locks the Cat row itself instead (same target
     * CheckoutHold::acquire() locks, see CLAUDE.md), which serializes two
     * concurrent createFromPayment() calls for the same cat exactly the
     * same way.
     *
     * Lost race (rare — CheckoutHold is supposed to prevent two visitors
     * ever reaching payment for the same cat at once, but TWINT's
     * auto-capture means a losing PaymentIntent can still end up
     * genuinely charged by the time this runs): the exact same
     * cancel/refund + DepositUnavailableNotification handling as
     * markPaid()'s own loseRace() below, reused as-is — kept, not
     * removed, because it's still the last line of defense. Deliberately
     * builds a transient, never-saved Deposit to hand it rather than
     * persisting one for the loser: update() on an unsaved model is a
     * harmless no-op (Eloquent checks $this->exists first), so
     * loseRace()'s own ->update(['status' => Unavailable]) call simply
     * does nothing here, while its gateway calls and notifications still
     * fire correctly off the transient instance's attributes.
     *
     * The client/staff confirmation emails (DepositConfirmedNotification/
     * DepositPaidNotification) are sent after this transaction commits,
     * never from inside it: the Stripe capture above is not reversible
     * from our side, but a DB transaction is — if a mail failure inside
     * DB::transaction() rolled it back, the client would be charged with
     * no Deposit row to show for it. See CLAUDE.md.
     *
     * QUEUE_CONNECTION=sync in production (see DEPLOY.md §1/§2 — no
     * daemon worker possible on Infomaniak's shared hosting), so these
     * notifications are not fire-and-forget: each one is its own
     * try/catch, logged on failure rather than left to bubble up and
     * turn a successful payment into a 5xx response to Stripe (which
     * would only cause a retry — createFromPayment() is idempotent via
     * provider_reference, so the retry would just find the Deposit
     * already there and never get a second chance to send the mail).
     * Client mail first, staff mail after: Infomaniak's SMTP costs
     * roughly 1-2s per message (an HTTP API would be a few hundred ms),
     * and staff mail goes out once per active admin — if the time budget
     * gets tight, staff waits, not the payer.
     *
     * @param  array<string, string>  $metadata
     */
    public function createFromPayment(array $metadata, string $paymentIntentId, ?int $amount, ?string $currency): ?Deposit
    {
        $catId = isset($metadata['cat_id']) ? (int) $metadata['cat_id'] : null;

        $deposit = DB::transaction(function () use ($metadata, $paymentIntentId, $amount, $currency, $catId): ?Deposit {
            if ($catId !== null) {
                Cat::whereKey($catId)->lockForUpdate()->first();

                $lostRace = Deposit::query()
                    ->where('cat_id', $catId)
                    ->where('status', DepositStatus::Paid)
                    ->lockForUpdate()
                    ->exists();

                if ($lostRace) {
                    $this->loseRace(new Deposit([
                        'cat_id' => $catId,
                        'name' => $metadata['name'] ?? '',
                        'email' => $metadata['email'] ?? '',
                        'provider_reference' => $paymentIntentId,
                        'locale' => $metadata['locale'] ?? null,
                    ]));

                    return null;
                }
            }

            $created = Deposit::create([
                'cat_id' => $catId,
                'name' => $metadata['name'] ?? '',
                'email' => $metadata['email'] ?? '',
                'phone' => $metadata['phone'] ?? null,
                'amount' => $amount,
                'currency' => $currency,
                'status' => DepositStatus::Paid,
                'provider' => 'stripe',
                'provider_reference' => $paymentIntentId,
                'locale' => $metadata['locale'] ?? null,
                'paid_at' => now(),
            ]);

            // TWINT auto-captures (no capture_method: manual support — see
            // StripeGateway::createPaymentIntent()), so by the time this
            // runs it may already be captured; a card authorization never
            // is yet at this point. Calling capture() on an
            // already-captured PaymentIntent would itself fail on
            // Stripe's side.
            if (! $this->gateway->isCaptured($created)) {
                $this->gateway->capture($created);
            }

            if ($catId !== null) {
                $created->cat->setStatus(CatStatus::Pending->value);
            }

            // The payment slot is no longer needed once a real
            // reservation exists — see CheckoutHold's own docblock.
            CheckoutHold::release($paymentIntentId);

            return $created;
        });

        if ($deposit !== null) {
            $this->sendConfirmationNotifications($deposit);
        }

        return $deposit;
    }

    /**
     * One try/catch per send, not a single one around both — a failure on
     * the staff mail must never suppress the client's own confirmation,
     * and vice versa. Logged explicitly on failure: a misconfigured
     * MAIL_FROM_ADDRESS (must match the authenticated Infomaniak SMTP
     * account, or anti-spoofing silently rejects the send) would
     * otherwise disappear into this catch block without a trace. See
     * CLAUDE.md.
     */
    private function sendConfirmationNotifications(Deposit $deposit): void
    {
        $this->sendClientConfirmation($deposit);

        try {
            Notification::send($this->activeStaff(), new DepositPaidNotification($deposit));
        } catch (Throwable $e) {
            Log::error('Failed to send DepositPaidNotification', [
                'deposit_id' => $deposit->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Just the client-facing half of sendConfirmationNotifications() above
     * — also called directly by ReconcileCheckouts (see CLAUDE.md) to
     * retry a confirmation that previously failed to send, without
     * re-notifying staff a second time about a Deposit they already got
     * DepositPaidNotification for when it was first created.
     */
    public function sendClientConfirmation(Deposit $deposit): void
    {
        $deposit->increment('confirmation_attempts');

        try {
            Notification::route('mail', $deposit->email)
                ->notify((new DepositConfirmedNotification($deposit))->locale($deposit->locale));

            $deposit->update(['confirmation_sent_at' => now()]);
        } catch (Throwable $e) {
            Log::error('Failed to send DepositConfirmedNotification', [
                'deposit_id' => $deposit->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * "Finalize the adoption": link the deposit to the owner it belongs to
     * (already resolved by the caller — existing or newly created) and, if
     * this deposit reserved a specific cat, move that cat to `adopte`. A
     * waiting-list deposit (no cat_id) still gets owner_id/finalized_at
     * set, just without a cat status transition.
     */
    public function finalize(Deposit $deposit, Owner $owner): void
    {
        $deposit->update([
            'owner_id' => $owner->id,
            'finalized_at' => now(),
        ]);

        if ($deposit->cat_id !== null) {
            $deposit->cat->setStatus(CatStatus::Adopted->value);
        }
    }

    /**
     * Finalizes an adoption entirely outside the normal Deposit-driven
     * flow — for an arrangement handled fully off-system (a gift, an
     * in-person sale with no online deposit, etc. — see CLAUDE.md).
     * super_admin only; deliberately still creates a Deposit rather than
     * just flipping the cat's status, so this stays an explicit, traceable
     * action that shows up in the same history/reporting as every other
     * adoption, instead of being invisible to anything that queries
     * Deposit.
     *
     * Reuses finalize() itself for the actual "link the owner, stamp
     * finalized_at, move the cat to adopte" work, rather than
     * restructuring it or duplicating its body — the only genuinely new
     * behavior here is building the minimal Deposit record to hand it.
     * finalize() didn't need to change at all: it already does exactly
     * the right thing for a fresh Deposit with a cat_id and no owner yet.
     *
     * Guarded the same way cancel()/markPaid() are — an early return
     * rather than an exception, since the caller (see
     * Admin\DepositController::finalizeDirectly()) already checks this
     * first and shows the user-facing error; this is just a
     * defense-in-depth no-op if ever called without that check.
     */
    public function finalizeDirectly(Cat $cat, Owner $owner): void
    {
        if ($cat->status === CatStatus::Adopted->value) {
            return;
        }

        $deposit = Deposit::create([
            'cat_id' => $cat->id,
            'name' => trim("{$owner->first_name} {$owner->last_name}"),
            'email' => $owner->email,
            'phone' => $owner->phone,
            'amount' => 0,
            'currency' => 'CHF',
            'status' => DepositStatus::Paid,
            'payment_method' => null,
            // Explicit and distinct from every real PSP/manual-payment
            // value (stripe/cash/bank_transfer/twint_manual) — this
            // Deposit exists purely as a paper trail, no money ever moved
            // through it.
            'provider' => 'manual_no_deposit',
            'paid_at' => now(),
        ]);

        $this->finalize($deposit, $owner);
    }

    /**
     * Undoes a paid deposit: releases the cat it holds — whether still
     * `en_attente` or already `adopte`, since that's exactly what this
     * action is for — back to `disponible`, and marks the deposit itself
     * `cancelled`. Fixes the gap where forcing a cat's status back to
     * `disponible` from Admin/Cats/Adoption/Form.vue alone left its
     * Deposit `paid`, so Deposit::blocksNewReservation() kept refusing
     * any new reservation for that cat forever (see CLAUDE.md).
     *
     * Leaves finalized_at/owner_id untouched on purpose — the deposit
     * keeps a historical record of what actually happened rather than
     * pretending it was never finalized/linked to an owner.
     *
     * Does not call PaymentGateway::refund() — this only concerns the
     * cat/deposit bookkeeping. If the client needs their money back, run
     * Admin\DepositController::refund() separately, and *before* calling
     * this: refund() itself requires status === Paid, a state this
     * method removes.
     *
     * Guarded the same way markPaid() guards its own idempotency — an
     * early return rather than an exception, since
     * Admin\DepositController::cancel() already checks this first and
     * shows the user-facing error (see its own docblock); this is just a
     * defense-in-depth no-op if ever called without that check.
     */
    public function cancel(Deposit $deposit): void
    {
        if ($deposit->status !== DepositStatus::Paid) {
            return;
        }

        $deposit->update(['status' => DepositStatus::Cancelled]);

        if ($deposit->cat_id !== null) {
            $deposit->cat->setStatus(CatStatus::Available->value);
        }
    }

    private function confirmPaid(Deposit $deposit, ?string $providerReference): void
    {
        // TWINT auto-captures (it doesn't support capture_method: manual —
        // see StripeGateway::createPaymentIntent()), so by the time this
        // runs it may already be captured; a card authorization never is
        // yet at this point. Calling capture() on an already-captured
        // PaymentIntent would itself fail on Stripe's side.
        if ($deposit->provider_reference !== null && ! $this->gateway->isCaptured($deposit)) {
            $this->gateway->capture($deposit);
        }

        $deposit->update([
            'status' => DepositStatus::Paid,
            'provider_reference' => $providerReference ?? $deposit->provider_reference,
            'paid_at' => now(),
        ]);

        // Normally already `en_attente` since reserve() ran at creation —
        // guarded rather than unconditional so this doesn't add a second,
        // redundant status row in the common case. Still runs it if that
        // never happened (e.g. a deposit created before this guard existed).
        if ($deposit->cat_id !== null && $deposit->cat->status !== CatStatus::Pending->value) {
            $deposit->cat->setStatus(CatStatus::Pending->value);
        }

        // ->locale() falls back to the app default when $deposit->locale is
        // null (admin-created deposits never go through the public checkout
        // flow that captures it) — see Public\DepositController::store().
        Notification::route('mail', $deposit->email)
            ->notify((new DepositConfirmedNotification($deposit))->locale($deposit->locale));

        Notification::send($this->activeStaff(), new DepositPaidNotification($deposit));
    }

    /**
     * Should be rare — Deposit::blocksNewReservation() already refuses a
     * new deposit once one is paid for a cat, so this only fires when two
     * authorizations for the same cat both reach markPaid() before either
     * one commits (see markPaid()'s own docblock).
     *
     * Releasing the losing PaymentIntent takes one of two forms, decided by
     * isCaptured(): a card authorization (capture_method: manual) is still
     * just held and can be cancelled outright, never charging the client —
     * but TWINT auto-captures (see StripeGateway::createPaymentIntent()),
     * so by the time markPaid() runs it may already have moved the money,
     * with nothing left to "cancel"; refund() is the only option there,
     * and the client genuinely was charged, however briefly. $refunded is
     * passed to DepositUnavailableNotification so its wording doesn't
     * overstate "you were never charged" for that case.
     */
    private function loseRace(Deposit $deposit): void
    {
        $refunded = false;

        if ($deposit->provider_reference !== null) {
            if ($this->gateway->isCaptured($deposit)) {
                $this->gateway->refund($deposit);
                $refunded = true;
            } else {
                $this->gateway->cancelAuthorization($deposit);
            }
        }

        $deposit->update(['status' => DepositStatus::Unavailable]);

        // Same ->locale() fallback as confirmPaid() above — staff always
        // gets the French version regardless (separate instance, no
        // ->locale() call).
        Notification::route('mail', $deposit->email)
            ->notify((new DepositUnavailableNotification($deposit, $refunded))->locale($deposit->locale));

        Notification::send($this->activeStaff(), new DepositUnavailableNotification($deposit, $refunded));
    }
}
