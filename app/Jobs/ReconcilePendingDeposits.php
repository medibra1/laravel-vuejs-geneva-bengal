<?php

namespace App\Jobs;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily safety net for a Deposit whose webhook never arrived (network
 * blip, Stripe retries exhausted, etc.) — see CLAUDE.md. Only looks at
 * deposits at least an hour old so it never races the webhook for one
 * that's simply still being paid.
 *
 * Also releases the cat held by a deposit whose Checkout session was
 * abandoned/expired (see Deposit::PENDING_EXPIRY_HOURS) rather than paid —
 * otherwise a cat would stay stuck at `en_attente` forever once a visitor
 * walks away from checkout.
 */
class ReconcilePendingDeposits implements ShouldQueue
{
    use Queueable;

    public function handle(PaymentGateway $gateway, DepositPaymentProcessor $processor): void
    {
        Deposit::query()
            ->where('status', DepositStatus::Pending)
            ->whereNotNull('provider_reference')
            ->where('created_at', '<=', now()->subHour())
            ->each(function (Deposit $deposit) use ($gateway, $processor): void {
                if ($gateway->isCheckoutPaid($deposit)) {
                    $processor->markPaid($deposit, (string) $deposit->provider_reference);

                    return;
                }

                if ($deposit->created_at->lte(now()->subHours(Deposit::PENDING_EXPIRY_HOURS))) {
                    $processor->expire($deposit);
                }
            });
    }
}
