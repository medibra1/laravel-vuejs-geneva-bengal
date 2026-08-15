<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\PaymentIntentTracking;
use App\Models\User;
use App\Notifications\DepositConfirmedNotification;
use App\Notifications\DepositPaidNotification;
use App\Notifications\DepositUnavailableNotification;
use App\Services\Payments\PaymentGateway;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Stripe\StripeClient;
use Tests\Doubles\FailingMailChannel;
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
 * @param  array<string, string>  $metadata
 */
function paymentIntentPayload(string $type, string $paymentIntentId, array $metadata, string $status = 'requires_capture'): string
{
    return json_encode([
        'id' => 'evt_test_'.$paymentIntentId,
        'object' => 'event',
        'type' => $type,
        'data' => [
            'object' => [
                'id' => $paymentIntentId,
                'object' => 'payment_intent',
                'status' => $status,
                'amount' => 50000,
                'currency' => 'chf',
                'metadata' => $metadata,
            ],
        ],
    ]);
}

/**
 * amount_capturable_updated is the manual-capture equivalent of "payment
 * succeeded" for a card PaymentIntent — see StripeGateway::handleWebhook()
 * and CLAUDE.md.
 *
 * @param  array<string, string>  $metadata
 */
function cardAuthorizedPayload(string $paymentIntentId, array $metadata): string
{
    return paymentIntentPayload('payment_intent.amount_capturable_updated', $paymentIntentId, $metadata, 'requires_capture');
}

/**
 * TWINT doesn't support capture_method: manual (see
 * StripeGateway::createPaymentIntent() and CLAUDE.md) — it auto-captures
 * the instant the client confirms in the app, so succeeded (not
 * amount_capturable_updated) is its "payment done" signal.
 *
 * @param  array<string, string>  $metadata
 */
function twintSucceededPayload(string $paymentIntentId, array $metadata): string
{
    return paymentIntentPayload('payment_intent.succeeded', $paymentIntentId, $metadata, 'succeeded');
}

/**
 * @return array<string, string>
 */
function checkoutMetadata(?int $catId = null, string $name = 'Marie Dupont', string $email = 'marie@example.com', ?string $locale = 'fr'): array
{
    return array_filter([
        'cat_id' => $catId === null ? null : (string) $catId,
        'name' => $name,
        'email' => $email,
        'locale' => $locale,
    ], fn (?string $value) => $value !== null);
}

function postSignedWebhook(string $payload, string $secret = 'whsec_test_secret'): TestResponse
{
    $header = signedStripeWebhookHeader($payload, $secret);

    return test()->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_Stripe-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
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

it('creates a paid deposit, captures the PaymentIntent, reserves the cat, sends the confirmation, and clears the tracking row on a card event', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);
    PaymentIntentTracking::query()->create(['payment_intent_id' => 'pi_test_123']);

    $payload = cardAuthorizedPayload('pi_test_123', checkoutMetadata(catId: $cat->id));
    $response = postSignedWebhook($payload);

    $response->assertNoContent();
    $deposit = Deposit::sole();
    expect($deposit->status)->toBe(DepositStatus::Paid);
    expect($deposit->provider_reference)->toBe('pi_test_123');
    expect($deposit->provider)->toBe('stripe');
    expect($deposit->cat_id)->toBe($cat->id);
    expect($deposit->amount)->toBe(50000);
    expect($deposit->currency)->toBe('CHF');
    expect($deposit->locale)->toBe('fr');
    expect($deposit->paid_at)->not->toBeNull();
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
    expect($this->gateway->capturedDepositIds)->toBe([$deposit->id]);
    expect(PaymentIntentTracking::query()->where('payment_intent_id', 'pi_test_123')->exists())->toBeFalse();
    expect($deposit->confirmation_sent_at)->not->toBeNull();
    expect($deposit->confirmation_attempts)->toBe(1);
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
    Notification::assertSentTo($admin, DepositPaidNotification::class);
});

it('is idempotent — a replayed webhook for the same PaymentIntent creates no duplicate deposit', function () {
    Notification::fake();
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);

    $payload = cardAuthorizedPayload('pi_test_replay', checkoutMetadata(catId: $cat->id));
    postSignedWebhook($payload)->assertNoContent();

    expect(Deposit::count())->toBe(1);
    // The first call already captured it once — captured here so the
    // replay assertion below can confirm no *second* capture happened,
    // rather than asserting an empty array (which would be true only by
    // never having captured at all).
    $capturedAfterFirstCall = $this->gateway->capturedDepositIds;
    Notification::fake(); // reset call log before the replay

    $response = postSignedWebhook($payload);

    $response->assertNoContent();
    expect(Deposit::count())->toBe(1);
    expect($this->gateway->capturedDepositIds)->toBe($capturedAfterFirstCall);
    Notification::assertNothingSent();
});

it('marks a TWINT deposit paid without calling capture() — it was already auto-captured', function () {
    Notification::fake();
    $this->gateway->isCapturedResult = true;
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $payload = twintSucceededPayload('pi_test_twint', checkoutMetadata());
    $response = postSignedWebhook($payload);

    $response->assertNoContent();
    $deposit = Deposit::sole();
    expect($deposit->status)->toBe(DepositStatus::Paid);
    expect($deposit->cat_id)->toBeNull();
    // Already captured by Stripe itself — calling capture() again would
    // fail on Stripe's side, see StripeGateway::capture()'s docblock.
    expect($this->gateway->capturedDepositIds)->toBeEmpty();
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
    Notification::assertSentTo($admin, DepositPaidNotification::class);
});

it('still creates the deposit when the confirmation email fails to send, with confirmation_sent_at left null', function () {
    $this->app->bind(MailChannel::class, FailingMailChannel::class);
    Log::spy();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);

    $payload = cardAuthorizedPayload('pi_test_mail_fail', checkoutMetadata(catId: $cat->id));
    $response = postSignedWebhook($payload);

    // The webhook still responds 200 regardless of the mail outcome — a
    // non-2xx would make Stripe retry, and the idempotency check by
    // provider_reference would then find the Deposit already there and
    // never get a second chance to send the mail (see CLAUDE.md).
    $response->assertNoContent();
    $deposit = Deposit::sole();
    expect($deposit->status)->toBe(DepositStatus::Paid);
    expect($deposit->confirmation_sent_at)->toBeNull();
    expect($deposit->confirmation_attempts)->toBe(1);
    // The Stripe capture is not reversible from our side, unlike a DB
    // transaction — a failed mail send must never roll back the Deposit
    // that records a payment that has already actually happened.
    expect($this->gateway->capturedDepositIds)->toBe([$deposit->id]);
    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message) => $message === 'Failed to send DepositConfirmedNotification');
    // Staff mail is unaffected by the client mail's own failure — sent
    // via the real MailChannel, only DepositConfirmedNotification is
    // intercepted by FailingMailChannel.
    expect($admin->fresh()->notifications()->where('type', DepositPaidNotification::class)->exists())->toBeTrue();
});

it('rejects a request with an invalid signature and creates no deposit', function () {
    $payload = cardAuthorizedPayload('pi_test_bad', checkoutMetadata());

    $response = postSignedWebhook($payload, 'wrong_secret');

    $response->assertNoContent();
    expect(Deposit::count())->toBe(0);
    expect($this->gateway->capturedDepositIds)->toBeEmpty();
});

it('ignores an event with no name/email in its metadata', function () {
    $payload = cardAuthorizedPayload('pi_test_no_metadata', []);

    $response = postSignedWebhook($payload);

    $response->assertNoContent();
    expect(Deposit::count())->toBe(0);
});

it('cancels a losing card PaymentIntent instead of capturing it, notifies client and staff, and creates no deposit for the loser', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    // Simulates the state the winning payment's own createFromPayment()
    // already left behind — loseRace() must never touch cat status
    // itself, whatever it is.
    $cat->setStatus(CatStatus::Pending->value);
    Deposit::factory()->paid()->create([
        'cat_id' => $cat->id,
        'provider_reference' => 'pi_test_winner',
    ]);

    $payload = cardAuthorizedPayload('pi_test_loser', checkoutMetadata(catId: $cat->id, name: 'Second Visitor', email: 'second@example.com', locale: 'en'));
    $response = postSignedWebhook($payload);

    $response->assertNoContent();
    // The entire point of this path: no Deposit row exists for the loser.
    expect(Deposit::where('email', 'second@example.com')->exists())->toBeFalse();
    expect(Deposit::count())->toBe(1);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
    // Never charged: cancelled, not captured.
    expect($this->gateway->cancelledProviderReferences)->toBe(['pi_test_loser']);
    expect($this->gateway->capturedDepositIds)->toBeEmpty();
    expect($this->gateway->refundedProviderReferences)->toBeEmpty();
    Notification::assertSentOnDemand(
        DepositUnavailableNotification::class,
        fn (DepositUnavailableNotification $notification) => $notification->refunded === false
            && $notification->locale === 'en',
    );
    Notification::assertSentTo(
        $admin,
        DepositUnavailableNotification::class,
        fn (DepositUnavailableNotification $notification) => $notification->refunded === false,
    );
});

it('refunds instead of cancelling a losing TWINT PaymentIntent, since it was already auto-captured, and creates no deposit for the loser', function () {
    Notification::fake();
    $this->gateway->isCapturedResult = true;
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);
    Deposit::factory()->paid()->create([
        'cat_id' => $cat->id,
        'provider_reference' => 'pi_test_twint_winner',
    ]);

    $payload = twintSucceededPayload('pi_test_twint_loser', checkoutMetadata(catId: $cat->id, name: 'Second Visitor', email: 'second@example.com'));
    $response = postSignedWebhook($payload);

    $response->assertNoContent();
    expect(Deposit::where('email', 'second@example.com')->exists())->toBeFalse();
    expect(Deposit::count())->toBe(1);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
    // No uncaptured authorization to release — TWINT had already moved
    // the money, so it's refunded rather than cancelled.
    expect($this->gateway->refundedProviderReferences)->toBe(['pi_test_twint_loser']);
    expect($this->gateway->cancelledProviderReferences)->toBeEmpty();
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
