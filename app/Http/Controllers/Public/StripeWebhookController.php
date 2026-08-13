<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    /**
     * Deliberately does not drain the queue itself (no inline
     * Artisan::call('queue:work', ...)) — Stripe expects a fast 2xx and
     * will retry aggressively if this endpoint is slow, and a queue:work
     * call here would also process unrelated jobs (newsletter/contact
     * emails, ReconcilePendingDeposits, ...) on a webhook request's
     * budget. Queued notifications (markPaid()'s DepositConfirmedNotification/
     * DepositPaidNotification) are drained by the scheduled /cron/run
     * endpoint instead — see routes/web.php and DEPLOY.md §4.
     */
    public function handle(Request $request, PaymentGateway $gateway, DepositPaymentProcessor $processor): Response
    {
        $result = $gateway->handleWebhook($request);

        if (! $result->handled || $result->depositId === null || $result->providerReference === null) {
            return response()->noContent();
        }

        $deposit = Deposit::find($result->depositId);

        // Unknown deposit — nothing left to do. markPaid() itself handles
        // the "already processed" case (Stripe retries webhooks it didn't
        // get a 2xx for).
        if ($deposit === null) {
            return response()->noContent();
        }

        $processor->markPaid($deposit, $result->providerReference);

        return response()->noContent();
    }
}
