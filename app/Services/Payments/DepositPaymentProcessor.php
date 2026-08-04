<?php

namespace App\Services\Payments;

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Notifications\DepositConfirmedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Shared by the webhook (normal path) and the daily reconciliation job
 * (catches a webhook that never arrived) so the two entry points can never
 * drift apart on what "a Deposit got paid" actually does.
 */
class DepositPaymentProcessor
{
    public function markPaid(Deposit $deposit, string $providerReference): void
    {
        if ($deposit->status === DepositStatus::Paid) {
            return;
        }

        $deposit->update([
            'status' => DepositStatus::Paid,
            'provider_reference' => $providerReference,
            'paid_at' => now(),
        ]);

        if ($deposit->cat_id !== null) {
            $deposit->cat->setStatus(CatStatus::Pending->value);
        }

        Notification::route('mail', $deposit->email)
            ->notify(new DepositConfirmedNotification($deposit));
    }
}
