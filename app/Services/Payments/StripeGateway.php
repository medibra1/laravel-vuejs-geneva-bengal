<?php

namespace App\Services\Payments;

use App\Enums\DepositStatus;
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
     */
    public function createPaymentIntent(Deposit $deposit): PaymentIntentResult
    {
        $intent = $this->client->paymentIntents->create([
            'amount' => $deposit->amount,
            'currency' => strtolower($deposit->currency),
            'payment_method_types' => ['card', 'twint'],
            'payment_method_options' => [
                'card' => ['capture_method' => 'manual'],
            ],
            // Read back on the webhook to identify which Deposit a
            // payment_intent.amount_capturable_updated /
            // payment_intent.succeeded event belongs to — Stripe has no
            // other reference to our own primary key.
            'metadata' => [
                'deposit_id' => (string) $deposit->id,
            ],
        ]);

        return new PaymentIntentResult(
            id: $intent->id,
            clientSecret: $intent->client_secret,
            url: route('deposits.return', $deposit),
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
     *   second time, harmlessly, right after markPaid() itself captures a
     *   card PaymentIntent — markPaid() is idempotent, so that repeat
     *   delivery is just a no-op.
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
        $depositId = $intent->metadata['deposit_id'] ?? null;

        if ($depositId === null) {
            return new PaymentWebhookResult(handled: false);
        }

        return new PaymentWebhookResult(
            handled: true,
            depositId: (int) $depositId,
            providerReference: $intent->id,
            status: DepositStatus::Paid,
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
