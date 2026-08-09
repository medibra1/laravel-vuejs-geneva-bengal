<?php

namespace App\Services\Payments;

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
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
     * Holds the cat as soon as a deposit is created (status `pending`), not
     * only once payment is confirmed — otherwise a second visitor could
     * start (and even pay) a deposit for the same cat before the first
     * payment is confirmed. See CatIsAvailableForDeposit for the matching
     * server-side guard against creating that second deposit in the first
     * place.
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
