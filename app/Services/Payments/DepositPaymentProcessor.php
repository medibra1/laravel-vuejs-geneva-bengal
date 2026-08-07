<?php

namespace App\Services\Payments;

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\Owner;
use App\Notifications\DepositConfirmedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Shared by the webhook (normal path), the daily reconciliation job
 * (catches a webhook that never arrived), and the admin's manual
 * "mark paid" action, so none of these entry points can ever drift apart
 * on what "a Deposit got paid" actually does.
 */
class DepositPaymentProcessor
{
    /**
     * Holds the cat as soon as a deposit is created (status `pending`), not
     * only once payment is confirmed — otherwise a second visitor could
     * start (and even pay) a deposit for the same cat before the first
     * payment is confirmed. See CatIsAvailableForDeposit for the matching
     * server-side guard against creating that second deposit in the first
     * place.
     */
    public function reserve(Deposit $deposit): void
    {
        if ($deposit->cat_id !== null) {
            $deposit->cat->setStatus(CatStatus::Pending->value);
        }
    }

    /**
     * $providerReference is null for a manually-recorded payment
     * (cash/bank_transfer/twint_manual) — there's no PSP reference to
     * store, so the existing value (if any) is left untouched.
     */
    public function markPaid(Deposit $deposit, ?string $providerReference = null): void
    {
        if ($deposit->status === DepositStatus::Paid) {
            return;
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
     * A pending deposit whose Stripe Checkout session has gone past its own
     * expiry (see Deposit::PENDING_EXPIRY_HOURS) can never be paid anymore
     * — release the cat it was holding back to `disponible`. Guarded on the
     * cat still being `en_attente` so this never clobbers a status set by
     * something else in the meantime (e.g. already finalized to `adopte`).
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
}
