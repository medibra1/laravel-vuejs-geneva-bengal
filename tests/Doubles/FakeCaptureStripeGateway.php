<?php

namespace Tests\Doubles;

use App\Models\Deposit;
use App\Services\Payments\CheckoutData;
use App\Services\Payments\PaymentIntentResult;
use App\Services\Payments\StripeGateway;

/**
 * Extends the real StripeGateway to inherit its handleWebhook() untouched
 * — pure local signature verification against the Stripe SDK's own
 * crypto, no network call, so it's worth testing for real (see CLAUDE.md)
 * — while overriding every method that would otherwise hit the Stripe API
 * with a fake. Lets StripeWebhookTest exercise
 * DepositPaymentProcessor::createFromPayment()'s capture()/cancelAuthorization()
 * side effects (triggered on every paid webhook, see CLAUDE.md) without
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
     * provider_reference of every cancelAuthorization()/refund() call —
     * DepositPaymentProcessor::createFromPayment()'s lost-race branch
     * calls these with a transient, unsaved Deposit (never persisted for
     * the loser, see CLAUDE.md), whose ->id is always null.
     *
     * @var array<int, string|null>
     */
    public array $cancelledProviderReferences = [];

    /**
     * @var array<int, string|null>
     */
    public array $refundedProviderReferences = [];

    /**
     * What isCaptured() reports — set true to simulate a TWINT
     * PaymentIntent (auto-captured, see CLAUDE.md) instead of the default
     * card one (still just authorized under capture_method: manual).
     */
    public bool $isCapturedResult = false;

    /**
     * @var array<int, CheckoutData>
     */
    public array $createPaymentIntentCalls = [];

    public function createPaymentIntent(CheckoutData $checkoutData): PaymentIntentResult
    {
        $this->createPaymentIntentCalls[] = $checkoutData;
        $fakeId = 'pi_test_fake_'.count($this->createPaymentIntentCalls);

        return new PaymentIntentResult(
            id: $fakeId,
            clientSecret: $fakeId.'_secret_test',
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
        $this->cancelledProviderReferences[] = $deposit->provider_reference;

        return true;
    }

    public function refund(Deposit $deposit): bool
    {
        $this->refundedDepositIds[] = $deposit->id;
        $this->refundedProviderReferences[] = $deposit->provider_reference;

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
