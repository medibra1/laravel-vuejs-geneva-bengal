<?php

namespace Tests\Doubles;

use App\Models\Deposit;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentWebhookResult;
use Illuminate\Http\Request;

/**
 * Real Stripe network calls (createCheckout, refund, isCheckoutPaid) can't
 * be exercised in tests without hitting the actual API — bound in place of
 * StripeGateway wherever a test only cares about our own controller/job
 * logic. handleWebhook() (pure local signature verification, no network
 * call) is instead tested directly against the real StripeGateway.
 */
class FakePaymentGateway implements PaymentGateway
{
    public bool $refundResult = true;

    public bool $checkoutPaidResult = false;

    /**
     * Set to simulate isCheckoutPaid() blowing up (network error, Stripe
     * API error, ...) — see ReconcilePendingDeposits' try/catch.
     */
    public ?\Throwable $checkoutPaidException = null;

    public function createCheckout(Deposit $deposit): CheckoutSession
    {
        return new CheckoutSession(id: 'cs_test_fake_'.$deposit->id, url: 'https://checkout.stripe.com/fake/'.$deposit->id);
    }

    public function handleWebhook(Request $request): PaymentWebhookResult
    {
        return new PaymentWebhookResult(handled: false);
    }

    public function refund(Deposit $deposit): bool
    {
        return $this->refundResult;
    }

    public function isCheckoutPaid(Deposit $deposit): bool
    {
        if ($this->checkoutPaidException !== null) {
            throw $this->checkoutPaidException;
        }

        return $this->checkoutPaidResult;
    }
}
