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
     * budget. In production QUEUE_CONNECTION=sync (see DEPLOY.md §1/§2 —
     * no daemon worker possible on Infomaniak's shared hosting), so
     * DepositPaymentProcessor::createFromPayment()'s own notifications
     * already run inline, synchronously, within this same request.
     *
     * No Deposit exists yet when this fires — see CLAUDE.md. Looked up by
     * provider_reference first: if one already exists, this event was
     * already processed (Stripe retries any webhook it didn't get a 2xx
     * for, and TWINT alone can also legitimately deliver two events for
     * the same payment — amount_capturable_updated doesn't apply to it,
     * but succeeded can still repeat) — nothing left to do, respond 200
     * and stop, since createFromPayment() itself only ever runs once per
     * PaymentIntent.
     */
    public function handle(Request $request, PaymentGateway $gateway, DepositPaymentProcessor $processor): Response
    {
        $result = $gateway->handleWebhook($request);

        if (! $result->handled || $result->paymentIntentId === null) {
            return response()->noContent();
        }

        $alreadyProcessed = Deposit::where('provider_reference', $result->paymentIntentId)->exists();

        if ($alreadyProcessed) {
            return response()->noContent();
        }

        $processor->createFromPayment($result->metadata, $result->paymentIntentId, $result->amount, $result->currency);

        return response()->noContent();
    }
}
