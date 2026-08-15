<?php

namespace App\Services\Payments;

use App\Models\Deposit;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly StripeClient $client,
        private readonly string $webhookSecret,
    ) {}

    /**
     * capture_method: manual is the core of this flow (see CLAUDE.md) — a
     * card authorization succeeds and holds the funds, but nothing is
     * actually debited until DepositPaymentProcessor::markPaid() captures
     * it, once the cat is confirmed to still be available.
     *
     * TWINT can't do this: Stripe rejects capture_method: manual outright
     * for that payment method type (confirmed against a real test-mode
     * PaymentIntent — "capture_method=manual is not supported by payment
     * method type twint"). TWINT settles the instant the client confirms
     * in the app, with no authorization-hold state to delay — so it's set
     * to manual capture only for card, via payment_method_options, leaving
     * TWINT on its only supported mode (automatic). markPaid() checks
     * isCaptured() before deciding whether there's anything left to
     * capture, and loseRace() checks it too before choosing
     * cancelAuthorization() (card, still just authorized) or refund()
     * (TWINT, already captured by the time we find out).
     *
     * Takes CheckoutData instead of a Deposit — the public checkout flow
     * no longer creates a Deposit row before payment is confirmed (see
     * CLAUDE.md). Every field the webhook will need to build the real
     * Deposit rides along as PaymentIntent metadata instead, since Stripe
     * is the only place this data lives until then.
     *
     * Called only from Public\DepositController::confirmIntent(), at the
     * "Pay" click — never on page load (see CLAUDE.md: no PaymentIntent,
     * no Stripe call at all, until the visitor actually commits to paying).
     */
    public function createPaymentIntent(CheckoutData $checkoutData): PaymentIntentResult
    {
        $intent = $this->client->paymentIntents->create([
            'amount' => $checkoutData->amount,
            'currency' => strtolower($checkoutData->currency),
            'payment_method_types' => ['card', 'twint'],
            'payment_method_options' => [
                'card' => ['capture_method' => 'manual'],
            ],
            // Read back by the webhook to build the Deposit once payment
            // is confirmed — no Deposit row exists yet at this point.
            'metadata' => array_filter([
                'cat_id' => $checkoutData->catId === null ? null : (string) $checkoutData->catId,
                'name' => $checkoutData->name,
                'email' => $checkoutData->email,
                'phone' => $checkoutData->phone,
                'locale' => $checkoutData->locale,
            ], fn (?string $value) => $value !== null),
        ]);

        return new PaymentIntentResult(
            id: $intent->id,
            clientSecret: $intent->client_secret,
        );
    }

    /**
     * The webhook is the only source of truth for a paid Deposit — never
     * the browser alone, which could close the tab right after
     * confirmPayment() without our backend ever hearing about it. See
     * CLAUDE.md.
     *
     * Two events both count as "the client has committed to paying":
     * - amount_capturable_updated: a card authorization succeeded and is
     *   awaiting our own capture() call (capture_method: manual).
     * - succeeded: TWINT was auto-captured the instant the client
     *   confirmed in the app (TWINT doesn't support manual capture at
     *   all, see createPaymentIntent()) — there's no "awaiting capture"
     *   step for it, this *is* its "payment done" signal. Also fires a
     *   second time, harmlessly, right after DepositPaymentProcessor
     *   itself captures a card PaymentIntent — createFromPayment() is
     *   idempotent (keyed on provider_reference), so that repeat delivery
     *   is just a no-op.
     *
     * No Deposit exists yet at this point (see CLAUDE.md) — everything
     * the caller needs to build one (checkout data, amount, currency)
     * comes straight off the PaymentIntent itself, not a database lookup —
     * see buildResultFromIntent().
     */
    public function handleWebhook(Request $request): PaymentWebhookResult
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature') ?? '',
                $this->webhookSecret,
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return new PaymentWebhookResult(handled: false);
        }

        if (! in_array($event->type, ['payment_intent.amount_capturable_updated', 'payment_intent.succeeded'], true)) {
            return new PaymentWebhookResult(handled: false);
        }

        /** @var PaymentIntent $intent */
        $intent = $event->data->object;

        return $this->buildResultFromIntent($intent);
    }

    /**
     * Polled by ReconcileCheckouts (see CLAUDE.md) for a stale
     * PaymentIntentTracking row — catches a webhook that never arrived: if
     * Stripe itself reports the PaymentIntent as paid, the checkout data
     * needed to build the Deposit (same shape handleWebhook() returns)
     * comes straight off this single retrieve() rather than a second round
     * trip. requires_capture/succeeded is the same "paid" criterion
     * handleWebhook() reacts to — a card authorization awaiting capture,
     * or a TWINT payment already auto-captured.
     */
    public function retrieveCheckoutData(string $paymentIntentId): PaymentWebhookResult
    {
        $intent = $this->client->paymentIntents->retrieve($paymentIntentId);

        if (! in_array($intent->status, ['requires_capture', 'succeeded'], true)) {
            return new PaymentWebhookResult(handled: false);
        }

        return $this->buildResultFromIntent($intent);
    }

    /**
     * name/email are the two fields a Deposit can't exist without;
     * missing either means this PaymentIntent wasn't created by our own
     * createPaymentIntent() (or Stripe truncated metadata some other way)
     * — treated as unhandled rather than building a broken Deposit.
     */
    private function buildResultFromIntent(PaymentIntent $intent): PaymentWebhookResult
    {
        $metadata = $intent->metadata->toArray();

        if (! isset($metadata['name'], $metadata['email'])) {
            return new PaymentWebhookResult(handled: false);
        }

        return new PaymentWebhookResult(
            handled: true,
            paymentIntentId: $intent->id,
            metadata: $metadata,
            amount: $intent->amount,
            currency: strtoupper($intent->currency),
        );
    }

    /**
     * Only ever called by DepositPaymentProcessor after it has confirmed
     * via isCaptured() that there's actually something left to capture —
     * calling this on an already-captured (TWINT) PaymentIntent would
     * itself fail on Stripe's side ("already captured").
     */
    public function capture(Deposit $deposit): bool
    {
        if ($deposit->provider_reference === null) {
            return false;
        }

        $this->client->paymentIntents->capture($deposit->provider_reference);

        return true;
    }

    /**
     * Only valid for a PaymentIntent isCaptured() reports as not yet
     * captured (a card authorization, capture_method: manual) — see
     * CLAUDE.md. DepositPaymentProcessor never calls this on an
     * already-captured (TWINT) PaymentIntent; it calls refund() instead.
     */
    public function cancelAuthorization(Deposit $deposit): bool
    {
        if ($deposit->provider_reference === null) {
            return false;
        }

        $this->client->paymentIntents->cancel($deposit->provider_reference);

        return true;
    }

    /**
     * Concerns an already-captured payment — unaffected by the move to
     * manual capture, other than provider_reference now being directly a
     * PaymentIntent id (it used to be a Checkout Session id, which needed
     * an extra retrieve() to find the payment_intent underneath it).
     */
    public function refund(Deposit $deposit): bool
    {
        if ($deposit->provider_reference === null) {
            return false;
        }

        $this->client->refunds->create(['payment_intent' => $deposit->provider_reference]);

        return true;
    }

    /**
     * requires_capture: authorized and awaiting our capture (see
     * capture() above) — succeeded: already captured through some other
     * path (e.g. a retried reconciliation run). Both count as "paid" for
     * the reconciliation job, which routes either case back through
     * DepositPaymentProcessor::markPaid() rather than duplicating its
     * win/lose-the-race logic here.
     */
    public function isCheckoutPaid(Deposit $deposit): bool
    {
        if ($deposit->provider_reference === null) {
            return false;
        }

        $intent = $this->client->paymentIntents->retrieve($deposit->provider_reference);

        return in_array($intent->status, ['requires_capture', 'succeeded'], true);
    }

    /**
     * succeeded is unambiguous here — reaching either capture() or
     * cancelAuthorization() at all already implies "the client committed
     * to paying" (see handleWebhook()); the only question left is whether
     * Stripe already moved the money on its own (TWINT) or is still just
     * holding an authorization (card, capture_method: manual).
     */
    public function isCaptured(Deposit $deposit): bool
    {
        if ($deposit->provider_reference === null) {
            return false;
        }

        $intent = $this->client->paymentIntents->retrieve($deposit->provider_reference);

        return $intent->status === 'succeeded';
    }
}
