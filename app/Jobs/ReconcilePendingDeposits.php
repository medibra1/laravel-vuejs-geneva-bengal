<?php

namespace App\Jobs;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Notifications\Concerns\NotifiesStaff;
use App\Notifications\StripeReconciliationIssueNotification;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Daily safety net for a Deposit whose webhook never arrived (network
 * blip, Stripe retries exhausted, etc.) — see CLAUDE.md. Only looks at
 * deposits at least an hour old so it never races the webhook for one
 * that's simply still being paid.
 *
 * Also releases the cat held by a deposit whose PaymentIntent authorization
 * was abandoned/expired (see Deposit::PENDING_EXPIRY_HOURS) rather than
 * paid — otherwise a cat would stay stuck at `en_attente` forever once a
 * visitor walks away from checkout.
 *
 * isCheckoutPaid() reporting true here (authorized or already captured, see
 * StripeGateway) still routes through DepositPaymentProcessor::markPaid() —
 * same atomic win/lose-the-race re-check as the webhook, rather than
 * duplicating that logic in this job.
 */
class ReconcilePendingDeposits implements ShouldQueue
{
    use NotifiesStaff, Queueable;

    public function handle(PaymentGateway $gateway, DepositPaymentProcessor $processor): void
    {
        Deposit::query()
            ->where('status', DepositStatus::Pending)
            ->whereNotNull('provider_reference')
            ->where('created_at', '<=', now()->subHour())
            ->each(function (Deposit $deposit) use ($gateway, $processor): void {
                try {
                    $isPaid = $gateway->isCheckoutPaid($deposit);
                } catch (Throwable $e) {
                    Notification::send(
                        $this->activeStaff(),
                        new StripeReconciliationIssueNotification($deposit, 'error', $e->getMessage()),
                    );

                    return;
                }

                if ($isPaid) {
                    $processor->markPaid($deposit, (string) $deposit->provider_reference);

                    return;
                }

                if ($deposit->created_at->lte(now()->subHours(Deposit::PENDING_EXPIRY_HOURS))) {
                    $processor->expire($deposit);

                    Notification::send(
                        $this->activeStaff(),
                        new StripeReconciliationIssueNotification($deposit, 'expired'),
                    );
                }
            });
    }
}
