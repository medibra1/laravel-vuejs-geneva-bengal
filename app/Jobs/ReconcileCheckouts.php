<?php

namespace App\Jobs;

use App\Enums\DepositStatus;
use App\Models\CheckoutHold;
use App\Models\Deposit;
use App\Notifications\Concerns\NotifiesStaff;
use App\Notifications\DepositConfirmationUndeliveredNotification;
use App\Notifications\StripeReconciliationIssueNotification;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Scheduled every 15 minutes (see routes/console.php). Two unrelated
 * safety nets, both needed because store() no longer creates a Deposit up
 * front and the webhook is now the only normal path that does (see
 * CLAUDE.md) — renamed from ReconcilePendingDeposits, which described a
 * mechanism (polling `deposits` rows stuck at `pending`) that no longer
 * exists on the public flow: nothing creates a pending Deposit with a
 * Stripe provider_reference anymore (confirmed by grep — the admin flow
 * only ever offers cash/bank_transfer/twint_manual, see
 * Admin\StoreDepositRequest), so Deposit::expire()/PENDING_EXPIRY_HOURS
 * were removed rather than kept around unused.
 *
 * 1. Expired CheckoutHold: the webhook that should have resolved it
 *    (paid or not) never arrived. Only holds actually past expiry are
 *    touched — a hold still within its TTL is simply mid-checkout, not
 *    stuck.
 * 2. Paid Deposit whose confirmation email never went out: in production
 *    QUEUE_CONNECTION=sync (see DEPLOY.md #1/#2), a failed SMTP send
 *    inside DepositPaymentProcessor::sendClientConfirmation() is not
 *    retried by a queue worker — this loop is the only thing that ever
 *    retries it.
 */
class ReconcileCheckouts implements ShouldQueue
{
    use NotifiesStaff, Queueable;

    /**
     * Above this many failed attempts, an address is treated as
     * persistently bad rather than retried forever every 15 minutes —
     * staff is notified once instead, to reach the client directly.
     */
    private const CONFIRMATION_MAX_ATTEMPTS = 5;

    public function handle(PaymentGateway $gateway, DepositPaymentProcessor $processor): void
    {
        $this->reconcileExpiredHolds($gateway, $processor);
        $this->retryFailedConfirmations($processor);
    }

    private function reconcileExpiredHolds(PaymentGateway $gateway, DepositPaymentProcessor $processor): void
    {
        $now = now();

        CheckoutHold::query()
            ->where(function ($query) use ($now) {
                $query->where('expires_at', '<=', $now)
                    ->orWhere('hard_expires_at', '<=', $now);
            })
            ->each(function (CheckoutHold $hold) use ($gateway, $processor): void {
                try {
                    $result = $gateway->retrieveCheckoutData($hold->payment_intent_id);
                } catch (Throwable $e) {
                    Notification::send(
                        $this->activeStaff(),
                        new StripeReconciliationIssueNotification($hold, $e->getMessage()),
                    );

                    return;
                }

                if ($result->handled) {
                    // The webhook that should have done this got lost —
                    // same idempotency guard as the webhook itself: if a
                    // Deposit already exists for this PaymentIntent (e.g.
                    // the webhook actually did arrive between this hold
                    // expiring and this job running), do nothing.
                    if (Deposit::where('provider_reference', $hold->payment_intent_id)->exists()) {
                        return;
                    }

                    $processor->createFromPayment($result->metadata, $hold->payment_intent_id, $result->amount, $result->currency);

                    return;
                }

                // Never paid — the cat is immediately reservable again for
                // someone else. No notification: an abandoned checkout is
                // the expected, common case, not something staff needs to
                // see every 15 minutes.
                $hold->delete();
            });
    }

    private function retryFailedConfirmations(DepositPaymentProcessor $processor): void
    {
        Deposit::query()
            ->where('status', DepositStatus::Paid)
            ->whereNull('confirmation_sent_at')
            ->where('confirmation_attempts', '<', self::CONFIRMATION_MAX_ATTEMPTS)
            ->each(function (Deposit $deposit) use ($processor): void {
                $processor->sendClientConfirmation($deposit);

                // sendClientConfirmation() mutates $deposit in place
                // (increment()/update() both update the in-memory
                // attributes, not just the row) — these reads see the
                // outcome of the attempt just made, no ->fresh() needed.
                if ($deposit->confirmation_sent_at === null && $deposit->confirmation_attempts >= self::CONFIRMATION_MAX_ATTEMPTS) {
                    Notification::send($this->activeStaff(), new DepositConfirmationUndeliveredNotification($deposit));
                }
            });
    }
}
