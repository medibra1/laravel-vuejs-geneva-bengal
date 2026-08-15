<?php

namespace Tests\Doubles;

use App\Models\Deposit;
use App\Services\Payments\CheckoutData;
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
     * API error, ...) — see ReconcileCheckouts' try/catch.
     */
    public ?\Throwable $checkoutPaidException = null;

    /**
     * What retrieveCheckoutData() returns for a given payment_intent_id —
     * lets a test control ReconcileCheckouts' volet 1 (paid/unpaid/error)
     * without a real Stripe call. Defaults to "not handled" (unpaid).
     *
     * @var array<string, PaymentWebhookResult>
     */
    public array $checkoutDataResults = [];

    /**
     * Set to simulate retrieveCheckoutData() blowing up (network error,
     * Stripe API error, ...) — see ReconcileCheckouts' try/catch.
     */
    public ?\Throwable $retrieveCheckoutDataException = null;

    /**
     * Ids of every Deposit capture()/cancelAuthorization() was called for
     * — lets a test assert a PaymentIntent was (or wasn't) actually
     * charged/released, without a real Stripe call. See the "lost the
     * race" tests in ReconcileCheckoutsTest.
     *
     * @var array<int, int>
     */
    public array $capturedDepositIds = [];

    /**
     * @var array<int, int>
     */
    public array $cancelledDepositIds = [];

    /**
     * provider_reference of every cancelAuthorization() call — tracked
     * separately from cancelledDepositIds because
     * Public\DepositController::store() now calls this with a transient,
     * unsaved Deposit carrying only provider_reference (a lost
     * CheckoutHold acquisition, before any real Deposit exists — see
     * CLAUDE.md), whose ->id is always null.
     *
     * @var array<int, string|null>
     */
    public array $cancelledProviderReferences = [];

    /**
     * Every CheckoutData createPaymentIntent() was called with — lets a
     * test assert a PaymentIntent was never created at all, e.g. when
     * Public\DepositController::store()'s own race re-check should have
     * rejected the request before ever reaching the gateway. No Deposit
     * exists yet at this point (see CLAUDE.md), so this tracks the
     * checkout data itself rather than a Deposit id.
     *
     * @var array<int, CheckoutData>
     */
    public array $createPaymentIntentCalls = [];

    /**
     * @var array<int, int>
     */
    public array $refundedDepositIds = [];

    /**
     * provider_reference of every refund() call — same reasoning as
     * cancelledProviderReferences above: DepositPaymentProcessor::createFromPayment()'s
     * lost-race branch calls this with a transient, unsaved Deposit
     * (never persisted for the loser, see CLAUDE.md), whose ->id is
     * always null.
     *
     * @var array<int, string|null>
     */
    public array $refundedProviderReferences = [];

    /**
     * What isCaptured() reports — set true to simulate a TWINT PaymentIntent
     * (auto-captured, see CLAUDE.md) instead of the default card one
     * (still just authorized under capture_method: manual).
     */
    public bool $isCapturedResult = false;

    /**
     * Set to simulate capture() blowing up (network error, Stripe API
     * error, ...) — lets a test exercise ReconcileCheckouts' per-row
     * resilience: one row throwing must never abort the rest of the batch.
     * One-shot (cleared to null right after throwing once) rather than a
     * persistent flag: this gateway instance is shared across every row in
     * a batch, so a persistent exception would fail *every* row's
     * capture(), not just the one a test means to simulate as broken.
     */
    public ?\Throwable $captureException = null;

    public function createPaymentIntent(CheckoutData $checkoutData): PaymentIntentResult
    {
        $this->createPaymentIntentCalls[] = $checkoutData;
        $fakeId = 'pi_test_fake_'.count($this->createPaymentIntentCalls);

        return new PaymentIntentResult(
            id: $fakeId,
            clientSecret: $fakeId.'_secret_test',
        );
    }

    public function handleWebhook(Request $request): PaymentWebhookResult
    {
        return new PaymentWebhookResult(handled: false);
    }

    public function retrieveCheckoutData(string $paymentIntentId): PaymentWebhookResult
    {
        if ($this->retrieveCheckoutDataException !== null) {
            throw $this->retrieveCheckoutDataException;
        }

        return $this->checkoutDataResults[$paymentIntentId] ?? new PaymentWebhookResult(handled: false);
    }

    public function capture(Deposit $deposit): bool
    {
        if ($this->captureException !== null) {
            $exception = $this->captureException;
            $this->captureException = null;

            throw $exception;
        }

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
