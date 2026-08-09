<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\User;
use App\Notifications\DepositConfirmedNotification;
use App\Notifications\DepositUnavailableNotification;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Stripe\StripeClient;
use Tests\Doubles\FakeCaptureStripeGateway;

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

/**
 * amount_capturable_updated is the manual-capture equivalent of "payment
 * succeeded" for a card PaymentIntent — see StripeGateway::handleWebhook()
 * and CLAUDE.md.
 */
function paymentIntentAmountCapturableUpdatedPayload(string $paymentIntentId, string $depositId, string $status = 'requires_capture'): string
{
    return json_encode([
        'id' => 'evt_test_'.$paymentIntentId,
        'object' => 'event',
        'type' => 'payment_intent.amount_capturable_updated',
        'data' => [
            'object' => [
                'id' => $paymentIntentId,
                'object' => 'payment_intent',
                'status' => $status,
                'amount' => 50000,
                'currency' => 'chf',
                'metadata' => ['deposit_id' => $depositId],
            ],
        ],
    ]);
}

/**
 * TWINT doesn't support capture_method: manual (see
 * StripeGateway::createPaymentIntent() and CLAUDE.md) — it auto-captures
 * the instant the client confirms in the app, so succeeded (not
 * amount_capturable_updated) is its "payment done" signal.
 */
function paymentIntentSucceededPayload(string $paymentIntentId, string $depositId): string
{
    return json_encode([
        'id' => 'evt_test_'.$paymentIntentId,
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => $paymentIntentId,
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'amount' => 50000,
                'currency' => 'chf',
                'metadata' => ['deposit_id' => $depositId],
            ],
        ],
    ]);
}

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

    // The "lost the race" path notifies active staff (see NotifiesStaff),
    // which looks these roles up even on the tests that never hit it.
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');

    // Real signature verification, faked network calls — see
    // FakeCaptureStripeGateway. Bound as a fixed instance (not a class
    // string) so the same object is reused across the HTTP request and
    // this test's own assertions against capturedDepositIds/
    // cancelledDepositIds.
    $this->gateway = new FakeCaptureStripeGateway(
        new StripeClient('sk_test_fake_key_for_test_suite'),
        'whsec_test_secret',
    );
    $this->app->instance(PaymentGateway::class, $this->gateway);
});

it('marks a deposit paid and captures its PaymentIntent on a validly signed payment_intent.amount_capturable_updated event', function () {
    Notification::fake();
    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_123',
    ]);

    $payload = paymentIntentAmountCapturableUpdatedPayload('pi_test_123', (string) $deposit->id);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($deposit->fresh()->provider_reference)->toBe('pi_test_123');
    expect($deposit->fresh()->paid_at)->not->toBeNull();
    expect($this->gateway->capturedDepositIds)->toBe([$deposit->id]);
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
});

/**
 * End-to-end version of the two tests above: unlike them (which start
 * from a Deposit::factory()->create() and fire the webhook in isolation),
 * this one goes through the real public creation endpoint first, so the
 * PaymentIntent id it webhooks back in is whatever
 * Public\DepositController::store() actually persisted as
 * provider_reference — not a value the test made up itself.
 */
it('covers the full public flow — deposit creation through webhook-confirmed capture and cat status change', function () {
    refreshApplicationWithLocale('fr');
    config([
        'honeypot.enabled' => false,
        'services.stripe.webhook_secret' => 'whsec_test_secret',
    ]);
    // refreshApplicationWithLocale() rebuilds the whole application
    // container, discarding this file's own beforeEach() bindings — see
    // the same pattern/comment in Public/DepositTest.php.
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
    $gateway = new FakeCaptureStripeGateway(
        new StripeClient('sk_test_fake_key_for_test_suite'),
        'whsec_test_secret',
    );
    $this->app->instance(PaymentGateway::class, $gateway);
    Notification::fake();
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);

    $createResponse = $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'cat_id' => $cat->id,
    ]);

    $createResponse->assertOk();
    $deposit = Deposit::sole();
    expect($deposit->status)->toBe(DepositStatus::Pending);
    expect($deposit->provider_reference)->toBe('pi_test_fake_'.$deposit->id);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);

    $payload = paymentIntentAmountCapturableUpdatedPayload($deposit->provider_reference, (string) $deposit->id);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $webhookResponse = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $webhookResponse->assertNoContent();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($deposit->fresh()->paid_at)->not->toBeNull();
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
    expect($gateway->capturedDepositIds)->toBe([$deposit->id]);
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
});

it('moves the linked cat to en_attente once its deposit is paid', function () {
    Notification::fake();
    $cat = Cat::factory()->create();
    $deposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_cat',
    ]);

    $payload = paymentIntentAmountCapturableUpdatedPayload('pi_test_cat', (string) $deposit->id);
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
        'provider_reference' => 'pi_test_bad',
    ]);

    $payload = paymentIntentAmountCapturableUpdatedPayload('pi_test_bad', (string) $deposit->id);
    $header = signedStripeWebhookHeader($payload, 'wrong_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
    expect($this->gateway->capturedDepositIds)->toBeEmpty();
});

it('marks a TWINT deposit paid without calling capture() — it was already auto-captured', function () {
    Notification::fake();
    $this->gateway->isCapturedResult = true;
    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_twint',
    ]);

    $payload = paymentIntentSucceededPayload('pi_test_twint', (string) $deposit->id);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($deposit->fresh()->paid_at)->not->toBeNull();
    // Already captured by Stripe itself — calling capture() again would
    // fail on Stripe's side, see StripeGateway::capture()'s docblock.
    expect($this->gateway->capturedDepositIds)->toBeEmpty();
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
});

it('refunds instead of cancelling a losing TWINT PaymentIntent, since it was already auto-captured', function () {
    Notification::fake();
    $this->gateway->isCapturedResult = true;
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);
    $winningDeposit = Deposit::factory()->paid()->create([
        'cat_id' => $cat->id,
        'provider_reference' => 'pi_test_twint_winner',
    ]);
    $losingDeposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_twint_loser',
    ]);

    $payload = paymentIntentSucceededPayload('pi_test_twint_loser', (string) $losingDeposit->id);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
    expect($losingDeposit->fresh()->status)->toBe(DepositStatus::Unavailable);
    expect($winningDeposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
    // No uncaptured authorization to release — TWINT had already moved
    // the money, so it's refunded rather than cancelled.
    expect($this->gateway->refundedDepositIds)->toBe([$losingDeposit->id]);
    expect($this->gateway->cancelledDepositIds)->toBeEmpty();
    // Wording must not claim "you were never charged" for this case.
    Notification::assertSentOnDemand(
        DepositUnavailableNotification::class,
        fn (DepositUnavailableNotification $notification) => $notification->refunded === true,
    );
    Notification::assertSentTo(
        $admin,
        DepositUnavailableNotification::class,
        fn (DepositUnavailableNotification $notification) => $notification->refunded === true,
    );
});

it('is idempotent — a retried webhook for an already-paid deposit does not notify or capture twice', function () {
    Notification::fake();
    $deposit = Deposit::factory()->paid()->create([
        'provider_reference' => 'pi_test_already',
    ]);

    $payload = paymentIntentAmountCapturableUpdatedPayload('pi_test_already', (string) $deposit->id);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
    Notification::assertNothingSent();
    expect($this->gateway->capturedDepositIds)->toBeEmpty();
});

it('ignores an event for an unknown deposit id', function () {
    $payload = paymentIntentAmountCapturableUpdatedPayload('pi_test_unknown', '999999');
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
});

it('cancels the losing deposit\'s PaymentIntent instead of capturing it, notifies client and staff, and leaves the cat status exactly as the winner left it', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    // Simulates the state the winning deposit's own confirmPaid() already
    // left behind (see DepositPaymentProcessor) — loseRace() must never
    // touch cat status itself, whatever it is.
    $cat->setStatus(CatStatus::Pending->value);
    $winningDeposit = Deposit::factory()->paid()->create([
        'cat_id' => $cat->id,
        'provider_reference' => 'pi_test_winner',
    ]);
    $losingDeposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_loser',
    ]);

    $payload = paymentIntentAmountCapturableUpdatedPayload('pi_test_loser', (string) $losingDeposit->id);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertNoContent();
    expect($losingDeposit->fresh()->status)->toBe(DepositStatus::Unavailable);
    expect($winningDeposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
    // Never charged: cancelled, not captured.
    expect($this->gateway->cancelledDepositIds)->toBe([$losingDeposit->id]);
    expect($this->gateway->capturedDepositIds)->toBeEmpty();
    expect($this->gateway->refundedDepositIds)->toBeEmpty();
    // Both audiences notified — the client (on-demand, mail only) and
    // staff (mail + database, surfaced in NotificationBell.vue) — with a
    // "never charged" wording (refunded: false), since a card
    // authorization was simply released, not captured then refunded.
    Notification::assertSentOnDemand(
        DepositUnavailableNotification::class,
        fn (DepositUnavailableNotification $notification) => $notification->refunded === false,
    );
    Notification::assertSentTo(
        $admin,
        DepositUnavailableNotification::class,
        fn (DepositUnavailableNotification $notification) => $notification->refunded === false,
    );
});
