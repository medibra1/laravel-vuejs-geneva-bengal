<?php

namespace App\Services\Payments;

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\Owner;
use App\Notifications\Concerns\NotifiesStaff;
use App\Notifications\DepositConfirmedNotification;
use App\Notifications\DepositUnavailableNotification;
use App\Notifications\NewDepositCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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
     * A pending deposit whose PaymentIntent authorization has gone past its
     * own expiry (see Deposit::PENDING_EXPIRY_HOURS) can never be paid
     * anymore — release the cat it was holding back to `disponible`.
     * Guarded on the cat still being `en_attente` so this never clobbers a
     * status set by something else in the meantime (e.g. already finalized
     * to `adopte`).
     */
    public function expire(Deposit $deposit): void
    {
        if ($deposit->status !== DepositStatus::Pending) {
            return;
        }

        $deposit->update(['status' => DepositStatus::Cancelled]);

        if ($deposit->cat_id !== null && $deposit->cat->status === CatStatus::Pending->value) {
            $deposit->cat->setStatus(CatStatus::Available->value);
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

        Notification::route('mail', $deposit->email)
            ->notify(new DepositConfirmedNotification($deposit));
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

        Notification::route('mail', $deposit->email)
            ->notify(new DepositUnavailableNotification($deposit, $refunded));

        Notification::send($this->activeStaff(), new DepositUnavailableNotification($deposit, $refunded));
    }
}
