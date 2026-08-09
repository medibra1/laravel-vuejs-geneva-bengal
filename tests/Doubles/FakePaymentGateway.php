<?php

namespace Tests\Doubles;

use App\Models\Deposit;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentIntentResult;
use App\Services\Payments\PaymentWebhookResult;
use Illuminate\Http\Request;

/**
 * Real Stripe network calls (createPaymentIntent, capture,
 * cancelAuthorization, refund, isCheckoutPaid) can't be exercised in tests
 * without hitting the actual API — bound in place of StripeGateway
 * wherever a test only cares about our own controller/job/service logic.
 * handleWebhook() (pure local signature verification, no network call) is
 * instead tested directly against the real StripeGateway.
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

    /**
     * Ids of every Deposit capture()/cancelAuthorization() was called for
     * — lets a test assert a PaymentIntent was (or wasn't) actually
     * charged/released, without a real Stripe call. See the "lost the
     * race" tests in ReconcilePendingDepositsTest.
     *
     * @var array<int, int>
     */
    public array $capturedDepositIds = [];

    /**
     * @var array<int, int>
     */
    public array $cancelledDepositIds = [];

    /**
     * Ids of every Deposit createPaymentIntent() was called for — lets a
     * test assert a PaymentIntent was never created at all, e.g. when
     * Public\DepositController::store()'s own race re-check should have
     * rejected the request before ever reaching the gateway.
     *
     * @var array<int, int>
     */
    public array $createPaymentIntentDepositIds = [];

    /**
     * @var array<int, int>
     */
    public array $refundedDepositIds = [];

    /**
     * What isCaptured() reports — set true to simulate a TWINT PaymentIntent
     * (auto-captured, see CLAUDE.md) instead of the default card one
     * (still just authorized under capture_method: manual).
     */
    public bool $isCapturedResult = false;

    public function createPaymentIntent(Deposit $deposit): PaymentIntentResult
    {
        $this->createPaymentIntentDepositIds[] = $deposit->id;

        return new PaymentIntentResult(
            id: 'pi_test_fake_'.$deposit->id,
            clientSecret: 'pi_test_fake_'.$deposit->id.'_secret_test',
            url: 'https://checkout.local/fake/'.$deposit->id,
        );
    }

    public function handleWebhook(Request $request): PaymentWebhookResult
    {
        return new PaymentWebhookResult(handled: false);
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

        return $this->refundResult;
    }

    public function isCheckoutPaid(Deposit $deposit): bool
    {
        if ($this->checkoutPaidException !== null) {
            throw $this->checkoutPaidException;
        }

        return $this->checkoutPaidResult;
    }

    public function isCaptured(Deposit $deposit): bool
    {
        return $this->isCapturedResult;
    }
}
