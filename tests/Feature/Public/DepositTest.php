<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\PaymentIntentTracking;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\NewDepositCreatedNotification;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Notification;
use Spatie\Honeypot\Honeypot;
use Spatie\Permission\Models\Role;
use Tests\Doubles\FakePaymentGateway;

// PaymentGateway is (re)bound inside each test, after
// refreshApplicationWithLocale() — that helper rebuilds the whole
// application container, which would otherwise discard a binding made in
// beforeEach().

beforeEach(function () {
    // Only the "does not notify staff" test below actually needs this
    // (assignRole('admin') requires the role to already exist in the
    // database — spatie/laravel-permission doesn't create it on the
    // fly), but every other test in this file tolerates it being here
    // regardless, so it's kept file-wide for the same reason the other
    // Deposit test files do this (see StripeWebhookTest.php).
    Role::findOrCreate('admin');
});

// --- deposits.store (checkout page, no PaymentIntent created here) --------

it('creates no Deposit and no PaymentIntent, and renders the checkout page carrying the form data', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    SiteSetting::set('deposit_amount', 50000);

    $response = $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'phone' => '+41 79 000 00 00',
    ]);

    // Nothing is created up front — no Deposit, and no PaymentIntent
    // either (see CLAUDE.md: that only happens at the "Pay" click, via
    // deposits.confirm-intent).
    expect(Deposit::count())->toBe(0);
    expect($gateway->createPaymentIntentCalls)->toBeEmpty();

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/DepositPay')
        ->where('catId', null)
        ->where('catName', null)
        ->where('catSlug', null)
        ->where('amount', 50000)
        ->where('currency', 'CHF')
        // Forced to this fixed fake value in tests/bootstrap.php.
        ->where('stripePublishableKey', 'pk_test_fake_key_for_test_suite')
        ->where('name', 'Marie Dupont')
        ->where('email', 'marie@example.com')
        ->where('phone', '+41 79 000 00 00')
    );
});

it('includes the cat name and slug in the checkout page props when the checkout is for a specific cat', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $cat = Cat::factory()->create(['name' => 'Simba']);

    $response = $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'cat_id' => $cat->id,
    ]);

    $response->assertInertia(fn ($page) => $page
        ->component('Public/DepositPay')
        ->where('catId', $cat->id)
        ->where('catName', 'Simba')
        ->where('catSlug', $cat->slug)
    );
});

it('leaves the cat disponible — it is only held once payment is actually confirmed', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);

    $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'cat_id' => $cat->id,
    ]);

    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
});

it('does not block two visitors from each reaching the checkout page for the same cat', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $cat = Cat::factory()->create();

    $first = $this->post('/fr/deposits', [
        'name' => 'First Visitor',
        'email' => 'first@example.com',
        'cat_id' => $cat->id,
    ]);
    $second = $this->post('/fr/deposits', [
        'name' => 'Second Visitor',
        'email' => 'second@example.com',
        'cat_id' => $cat->id,
    ]);

    $first->assertOk();
    $second->assertOk();
    $second->assertSessionDoesntHaveErrors(['cat_id']);
});

it('allows a new checkout for a cat whose only existing deposit is pending (not yet paid) — only a paid deposit blocks a reservation', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $cat = Cat::factory()->create();
    Deposit::factory()->create(['cat_id' => $cat->id, 'status' => DepositStatus::Pending]);

    $response = $this->post('/fr/deposits', [
        'name' => 'Second Visitor',
        'email' => 'second@example.com',
        'cat_id' => $cat->id,
    ]);

    $response->assertSessionDoesntHaveErrors(['cat_id']);
    $response->assertOk();
});

it('refuses a checkout for a cat that already has one paid deposit', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    $cat = Cat::factory()->create();
    Deposit::factory()->paid()->create(['cat_id' => $cat->id]);

    $response = $this->post('/fr/deposits', [
        'name' => 'Second Visitor',
        'email' => 'second@example.com',
        'cat_id' => $cat->id,
    ]);

    $response->assertSessionHasErrors(['cat_id']);
    expect(session('errors')->get('cat_id')[0])->toBe('Ce chaton a déjà été réservé.');
    expect($gateway->createPaymentIntentCalls)->toBeEmpty();
});

it('validates required fields', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);

    $response = $this->post('/fr/deposits', []);

    $response->assertSessionHasErrors(['name', 'email']);
});

it('silently discards spam submissions caught by the honeypot', function () {
    refreshApplicationWithLocale('fr');
    $honeypot = app(Honeypot::class);

    $response = $this->post('/fr/deposits', [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        $honeypot->nameFieldName() => 'i-am-a-bot',
        $honeypot->validFromFieldName() => $honeypot->encryptedValidFrom(),
    ]);

    $response->assertOk();
    expect(Deposit::count())->toBe(0);
});

it('does not notify staff when a public visitor merely reaches the checkout page — nothing has been paid yet', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    Notification::fake();
    $activeAdmin = User::factory()->create(['is_active' => true]);
    $activeAdmin->assignRole('admin');

    $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
    ]);

    Notification::assertNotSentTo($activeAdmin, NewDepositCreatedNotification::class);
});

// --- deposits.confirm-intent (the actual "Pay" click) ----------------------

it('creates a PaymentIntent, writes a tracking row before returning the client secret, and returns it as JSON', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    SiteSetting::set('deposit_amount', 50000);

    $response = $this->postJson('/fr/deposits/confirm-intent', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'phone' => '+41 79 000 00 00',
    ]);

    $response->assertOk();
    $response->assertJson([
        'paymentIntentId' => 'pi_test_fake_1',
        'clientSecret' => 'pi_test_fake_1_secret_test',
    ]);

    $checkoutData = $gateway->createPaymentIntentCalls[0];
    expect($checkoutData->catId)->toBeNull();
    expect($checkoutData->name)->toBe('Marie Dupont');
    expect($checkoutData->email)->toBe('marie@example.com');
    expect($checkoutData->phone)->toBe('+41 79 000 00 00');
    expect($checkoutData->locale)->toBe('fr');
    expect($checkoutData->amount)->toBe(50000);
    expect($checkoutData->currency)->toBe('CHF');

    // Written before the response above is even asserted here — this
    // request already completed by the time the test sees it, but the
    // controller itself writes the row before building the JSON response
    // (see CLAUDE.md and PaymentIntentTracking's own docblock).
    expect(PaymentIntentTracking::query()->where('payment_intent_id', 'pi_test_fake_1')->exists())->toBeTrue();
});

it('passes the visitor\'s active locale to the gateway as checkout data', function () {
    refreshApplicationWithLocale('en');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    $this->postJson('/en/deposits/confirm-intent', [
        'name' => 'John Smith',
        'email' => 'john@example.com',
    ]);

    expect($gateway->createPaymentIntentCalls[0]->locale)->toBe('en');
});

it('passes the chosen cat_id to the gateway as checkout data', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    $cat = Cat::factory()->create();

    $this->postJson('/fr/deposits/confirm-intent', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'cat_id' => $cat->id,
    ]);

    expect($gateway->createPaymentIntentCalls[0]->catId)->toBe($cat->id);
});

it('allows two visitors to each get their own PaymentIntent for the same cat — arbitration happens later, at payment confirmation', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    $cat = Cat::factory()->create();

    $first = $this->postJson('/fr/deposits/confirm-intent', [
        'name' => 'First Visitor',
        'email' => 'first@example.com',
        'cat_id' => $cat->id,
    ]);
    $second = $this->postJson('/fr/deposits/confirm-intent', [
        'name' => 'Second Visitor',
        'email' => 'second@example.com',
        'cat_id' => $cat->id,
    ]);

    $first->assertOk();
    $second->assertOk();
    expect($gateway->createPaymentIntentCalls)->toHaveCount(2);
    expect(PaymentIntentTracking::query()->count())->toBe(2);
});

it('refuses to create a PaymentIntent for a cat that already has one paid deposit, without ever calling the gateway', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    $cat = Cat::factory()->create();
    Deposit::factory()->paid()->create(['cat_id' => $cat->id]);

    $response = $this->postJson('/fr/deposits/confirm-intent', [
        'name' => 'Second Visitor',
        'email' => 'second@example.com',
        'cat_id' => $cat->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['cat_id']);
    expect($gateway->createPaymentIntentCalls)->toBeEmpty();
    expect(PaymentIntentTracking::query()->count())->toBe(0);
    expect(Deposit::count())->toBe(1);
});

it('validates required fields on confirm-intent', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);

    $response = $this->postJson('/fr/deposits/confirm-intent', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'email']);
});

// --- deposits.return ---------------------------------------------------

it('shows a waiting/status page for a payment intent with no deposit yet, without ever trusting the redirect alone', function () {
    refreshApplicationWithLocale('fr');

    $response = $this->get('/fr/deposits/return/pi_unknown?status=success');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/DepositReturn')
        ->where('depositStatus', 'pending')
        ->where('email', null)
    );
});

it('shows the real deposit status and email once one exists for the given payment intent', function () {
    refreshApplicationWithLocale('fr');
    $deposit = Deposit::factory()->paid()->create(['provider_reference' => 'pi_known', 'email' => 'marie@example.com']);

    $response = $this->get('/fr/deposits/return/pi_known?status=success');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/DepositReturn')
        ->where('depositStatus', $deposit->status->value)
        ->where('email', 'marie@example.com')
    );
});
