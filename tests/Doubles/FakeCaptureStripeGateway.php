<?php

namespace Tests\Doubles;

use App\Models\Deposit;
use App\Services\Payments\PaymentIntentResult;
use App\Services\Payments\StripeGateway;

/**
 * Extends the real StripeGateway to inherit its handleWebhook() untouched
 * — pure local signature verification against the Stripe SDK's own
 * crypto, no network call, so it's worth testing for real (see CLAUDE.md)
 * — while overriding every method that would otherwise hit the Stripe API
 * with a fake. Lets StripeWebhookTest exercise
 * DepositPaymentProcessor::markPaid()'s capture()/cancelAuthorization()
 * side effects (now triggered on every paid webhook, see CLAUDE.md) without
 * ever making a real Stripe call with the test suite's fake API key.
 */
class FakeCaptureStripeGateway extends StripeGateway
{
    /**
     * @var array<int, int>
     */
    public array $capturedDepositIds = [];

    /**
     * @var array<int, int>
     */
    public array $cancelledDepositIds = [];

    /**
     * @var array<int, int>
     */
    public array $refundedDepositIds = [];

    /**
     * What isCaptured() reports — set true to simulate a TWINT
     * PaymentIntent (auto-captured, see CLAUDE.md) instead of the default
     * card one (still just authorized under capture_method: manual).
     */
    public bool $isCapturedResult = false;

    public function createPaymentIntent(Deposit $deposit): PaymentIntentResult
    {
        return new PaymentIntentResult(
            id: 'pi_test_fake_'.$deposit->id,
            clientSecret: 'pi_test_fake_'.$deposit->id.'_secret_test',
            url: 'https://checkout.local/fake/'.$deposit->id,
        );
    }

    public function capture(Deposit $deposit): bool
    {
        $this->capturedDepositIds[] = $deposit->id;

        return true;
    }

    public function cancelAuthorization(Deposit $deposit): bool
    {
        $this->cancelledDepositIds[] = $deposit->id;

        return true;
    }

    public function refund(Deposit $deposit): bool
    {
        $this->refundedDepositIds[] = $deposit->id;

        return true;
    }

    public function isCheckoutPaid(Deposit $deposit): bool
    {
        return false;
    }

    public function isCaptured(Deposit $deposit): bool
    {
        return $this->isCapturedResult;
    }
}
