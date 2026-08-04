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
