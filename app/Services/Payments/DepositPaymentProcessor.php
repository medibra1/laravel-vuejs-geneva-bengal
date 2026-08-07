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

        if ($deposit->cat_id !== null) {
            $deposit->cat->setStatus(CatStatus::Pending->value);
        }

        Notification::route('mail', $deposit->email)
            ->notify(new DepositConfirmedNotification($deposit));
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
