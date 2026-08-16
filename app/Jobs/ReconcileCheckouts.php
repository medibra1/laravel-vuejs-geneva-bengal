<?php

namespace App\Jobs;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\PaymentIntentTracking;
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
 * safety nets:
 *
 * 1. Stale PaymentIntentTracking rows: a PaymentIntent was created (see
 *    Public\DepositController::confirmIntent()) but neither the webhook nor
 *    this job has resolved it yet — could mean the webhook is genuinely
 *    lost, or simply that the visitor hasn't finished paying yet (see
 *    CLAUDE.md: no lock/TTL on this table, unlike the CheckoutHold it
 *    replaced). GRACE_PERIOD_MINUTES exists precisely to tell those two
 *    apart — only a row old enough that a normal payment would have
 *    resolved it by now is treated as possibly stuck.
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
     * A normal payment resolves (webhook arrives) within seconds — this is
     * a generous margin before treating a still-unresolved PaymentIntent as
     * possibly stuck rather than simply mid-checkout. Lowered from 30 to 5
     * minutes (2026-08-15): a paid-but-orphaned PaymentIntent (webhook
     * missed entirely — e.g. `stripe listen` not running) leaves the cat
     * unavailable to a second payer with no Deposit to show for it and no
     * refund/cancel issued until this job catches up — 30 minutes was too
     * long a window for that to sit unresolved, both in practice and for
     * manually testing the reconciliation path itself. Still runs every 15
     * minutes (see routes/console.php) — this only controls which rows are
     * *eligible* once the job does run, not how often it runs.
     */
    private const GRACE_PERIOD_MINUTES = 15;

    /**
     * Above this many failed attempts, an address is treated as
     * persistently bad rather than retried forever every 15 minutes —
     * staff is notified once instead, to reach the client directly.
     */
    private const CONFIRMATION_MAX_ATTEMPTS = 5;

    public function handle(PaymentGateway $gateway, DepositPaymentProcessor $processor): void
    {
        $this->reconcileStaleTracking($gateway, $processor);
        $this->retryFailedConfirmations($processor);
    }

    private function reconcileStaleTracking(PaymentGateway $gateway, DepositPaymentProcessor $processor): void
    {
        PaymentIntentTracking::query()
            ->where('created_at', '<=', now()->subMinutes(self::GRACE_PERIOD_MINUTES))
            ->each(function (PaymentIntentTracking $tracking) use ($gateway, $processor): void {
                // The webhook may have arrived between this row going stale
                // and this job running — same idempotency guard as the
                // webhook itself. createFromPayment() deletes this tracking
                // row once it builds the Deposit, so ordinarily this branch
                // wouldn't even see the row again — kept as defense in
                // depth against any ordering where the row outlives the
                // Deposit it tracks.
                if (Deposit::where('provider_reference', $tracking->payment_intent_id)->exists()) {
                    $tracking->delete();

                    return;
                }

                // Wraps both the Stripe read (retrieveCheckoutData) and the
                // write side (createFromPayment, which can itself call
                // Stripe again via capture()/refund()/cancelAuthorization())
                // in one try/catch — a Throwable from either previously
                // escaped each()'s callback uncaught, which aborts the
                // *entire* chunk/batch (see BuildsQueries::chunk()'s plain
                // do/while loop, no per-row isolation), silently starving
                // every other stale row queued behind the failing one in
                // the same run. See CLAUDE.md — this is what let one bad
                // row (an already-refunded PaymentIntent retried because
                // its tracking row was never cleared) block reconciliation
                // for everything after it, run after run.
                try {
                    $result = $gateway->retrieveCheckoutData($tracking->payment_intent_id);

                    if ($result->handled) {
                        // createFromPayment() deletes the tracking row
                        // itself once the PaymentIntent is resolved, won or
                        // lost — see CLAUDE.md.
                        $processor->createFromPayment($result->metadata, $tracking->payment_intent_id, $result->amount, $result->currency);

                        return;
                    }
                } catch (Throwable $e) {
                    Notification::send(
                        $this->activeStaff(),
                        new StripeReconciliationIssueNotification($tracking, $e->getMessage()),
                    );

                    return;
                }

                // Never paid — most likely an abandoned checkout, the
                // expected, common case. No notification, and the row is
                // simply removed: nothing further to track.
                $tracking->delete();
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
