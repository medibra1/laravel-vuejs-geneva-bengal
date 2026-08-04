<?php

namespace App\Services\Payments;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly StripeClient $client,
        private readonly string $webhookSecret,
    ) {}

    public function createCheckout(Deposit $deposit): CheckoutSession
    {
        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card', 'twint'],
            'customer_email' => $deposit->email,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($deposit->currency),
                    'unit_amount' => $deposit->amount,
                    'product_data' => [
                        'name' => $deposit->cat_id
                            ? "Acompte — {$deposit->cat->name}"
                            : "Acompte — liste d'attente Geneva Bengal",
                    ],
                ],
                'quantity' => 1,
            ]],
            // Read back on the webhook to identify which Deposit a
            // checkout.session.completed event belongs to — Stripe has no
            // other reference to our own primary key.
            'metadata' => [
                'deposit_id' => (string) $deposit->id,
            ],
            'success_url' => route('deposits.return', $deposit).'?status=success',
            'cancel_url' => route('deposits.return', $deposit).'?status=cancelled',
        ]);

        return new CheckoutSession(id: $session->id, url: $session->url);
    }

    /**
     * The webhook is the only source of truth for a paid Deposit — never
     * the success_url redirect, which a browser can trivially reach
     * without ever having paid. See CLAUDE.md.
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

        if ($event->type !== 'checkout.session.completed') {
            return new PaymentWebhookResult(handled: false);
        }

        /** @var Session $session */
        $session = $event->data->object;
        $depositId = $session->metadata['deposit_id'] ?? null;

        if ($depositId === null) {
            return new PaymentWebhookResult(handled: false);
        }

        return new PaymentWebhookResult(
            handled: true,
            depositId: (int) $depositId,
            providerReference: $session->id,
            status: DepositStatus::Paid,
        );
    }

    public function refund(Deposit $deposit): bool
    {
        if ($deposit->provider_reference === null) {
            return false;
        }

        $session = $this->client->checkout->sessions->retrieve($deposit->provider_reference);

        if ($session->payment_intent === null) {
            return false;
        }

        $this->client->refunds->create(['payment_intent' => $session->payment_intent]);

        return true;
    }

    public function isCheckoutPaid(Deposit $deposit): bool
    {
        if ($deposit->provider_reference === null) {
            return false;
        }

        $session = $this->client->checkout->sessions->retrieve($deposit->provider_reference);

        return $session->payment_status === 'paid';
    }
}
