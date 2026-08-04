<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Deposit;
use App\Notifications\DepositConfirmedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Stripe signs webhooks with `t=<timestamp>,v1=<hmac_sha256(secret, "{t}.{payload}")>`
 * — pure local crypto, no network call, so this is reproducible without
 * hitting Stripe. See Stripe\Webhook::constructEvent() /
 * Stripe\WebhookSignature::verifyHeader().
 */
function signedStripeWebhookHeader(string $payload, string $secret): string
{
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return "t={$timestamp},v1={$signature}";
}

function checkoutCompletedPayload(string $sessionId, string $depositId): string
{
    return json_encode([
        'id' => 'evt_test_'.$sessionId,
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => $sessionId,
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_'.$sessionId,
                'metadata' => ['deposit_id' => $depositId],
            ],
        ],
    ]);
}

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
});

it('marks a deposit paid on a validly signed checkout.session.completed event', function () {
    Notification::fake();
    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'cs_test_123',
    ]);

    $payload = checkoutCompletedPayload('cs_test_123', (string) $deposit->id);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($deposit->fresh()->provider_reference)->toBe('cs_test_123');
    expect($deposit->fresh()->paid_at)->not->toBeNull();
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
});

it('moves the linked cat to en_attente once its deposit is paid', function () {
    Notification::fake();
    $cat = Cat::factory()->create();
    $deposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'status' => DepositStatus::Pending,
        'provider_reference' => 'cs_test_cat',
    ]);

    $payload = checkoutCompletedPayload('cs_test_cat', (string) $deposit->id);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
});

it('rejects a request with an invalid signature and leaves the deposit untouched', function () {
    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'cs_test_bad',
    ]);

    $payload = checkoutCompletedPayload('cs_test_bad', (string) $deposit->id);
    $header = signedStripeWebhookHeader($payload, 'wrong_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

it('is idempotent — a retried webhook for an already-paid deposit does not notify twice', function () {
    Notification::fake();
    $deposit = Deposit::factory()->paid()->create([
        'provider_reference' => 'cs_test_already',
    ]);

    $payload = checkoutCompletedPayload('cs_test_already', (string) $deposit->id);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
    Notification::assertNothingSent();
});

it('ignores an event for an unknown deposit id', function () {
    $payload = checkoutCompletedPayload('cs_test_unknown', '999999');
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
});
