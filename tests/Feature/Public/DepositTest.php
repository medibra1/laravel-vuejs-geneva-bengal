<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\CheckoutHold;
use App\Models\Deposit;
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

it('creates no Deposit row and renders the integrated checkout page with a PaymentIntent client secret', function () {
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

    // No Deposit exists at all at this point — it's only built later by
    // the webhook, from the checkout data carried in the PaymentIntent's
    // own metadata (see CLAUDE.md). An abandoned checkout therefore never
    // leaves a row behind.
    expect(Deposit::count())->toBe(0);
    expect($gateway->createPaymentIntentCalls)->toHaveCount(1);
    $checkoutData = $gateway->createPaymentIntentCalls[0];
    expect($checkoutData->catId)->toBeNull();
    expect($checkoutData->name)->toBe('Marie Dupont');
    expect($checkoutData->email)->toBe('marie@example.com');
    expect($checkoutData->phone)->toBe('+41 79 000 00 00');
    expect($checkoutData->locale)->toBe('fr');
    expect($checkoutData->amount)->toBe(50000);
    expect($checkoutData->currency)->toBe('CHF');

    // No more cross-origin redirect to a Stripe-hosted page — the
    // PaymentIntent is confirmed client-side, on this same response, via
    // a Stripe.js Payment Element mounted with clientSecret (see CLAUDE.md).
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/DepositPay')
        ->where('paymentIntentId', 'pi_test_fake_1')
        ->where('clientSecret', 'pi_test_fake_1_secret_test')
        // Forced to this fixed fake value in tests/bootstrap.php.
        ->where('stripePublishableKey', 'pk_test_fake_key_for_test_suite')
        ->where('catName', null)
        ->where('amount', 50000)
        ->where('currency', 'CHF')
    );
});

it('passes the visitor\'s active locale to the gateway as checkout data', function () {
    refreshApplicationWithLocale('en');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    $this->post('/en/deposits', [
        'name' => 'John Smith',
        'email' => 'john@example.com',
    ]);

    // Neither the Stripe webhook nor the daily reconciliation job that
    // eventually confirm payment have any notion of "the current
    // visitor's language" — this is the only point where it's known, see
    // Public\DepositController::store(). Carried in PaymentIntent
    // metadata now, not a Deposit column read back later.
    expect($gateway->createPaymentIntentCalls[0]->locale)->toBe('en');
});

it('includes the cat name in the checkout page props when the checkout is for a specific cat', function () {
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
        ->where('catName', 'Simba')
    );
});

it('passes the chosen cat_id to the gateway as checkout data', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    $cat = Cat::factory()->create();

    $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'cat_id' => $cat->id,
    ]);

    expect($gateway->createPaymentIntentCalls[0]->catId)->toBe($cat->id);
});

it('acquires a checkout hold on the cat when one is given', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $cat = Cat::factory()->create();

    $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'cat_id' => $cat->id,
    ]);

    $hold = CheckoutHold::query()->where('cat_id', $cat->id)->sole();
    expect($hold->payment_intent_id)->toBe('pi_test_fake_1');
});

it('does not acquire any checkout hold for a waiting-list checkout — there is no single cat to protect', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);

    $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
    ]);

    expect(CheckoutHold::query()->count())->toBe(0);
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

    // CheckoutHold protects the payment slot, not the cat's public
    // availability (see CheckoutHold's own docblock and CLAUDE.md) —
    // DepositPaymentProcessor::confirmPaid() (via the webhook) is what
    // actually reserves the cat, exactly once paid.
    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
});

it('refuses a second checkout for a cat that already has a live checkout hold, cancels its PaymentIntent, and creates no extra hold', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    $cat = Cat::factory()->create();

    $first = $this->post('/fr/deposits', [
        'name' => 'First Visitor',
        'email' => 'first@example.com',
        'cat_id' => $cat->id,
    ]);
    $first->assertOk();

    $second = $this->post('/fr/deposits', [
        'name' => 'Second Visitor',
        'email' => 'second@example.com',
        'cat_id' => $cat->id,
    ]);

    $second->assertSessionHasErrors(['cat_id']);
    // Distinct message from "already reserved" — the cat itself is still
    // available, someone else is simply mid-payment for it right now.
    // French because the request went through /fr/deposits — see
    // lang/fr.json.
    expect(session('errors')->get('cat_id')[0])
        ->toBe('Une autre personne est en train de payer pour ce chaton. Veuillez réessayer dans quelques minutes.');
    // A PaymentIntent was created for the second attempt (the hold can
    // only be checked once it exists, see Public\DepositController::store())
    // but immediately cancelled — never left dangling, never confirmable.
    expect($gateway->createPaymentIntentCalls)->toHaveCount(2);
    expect($gateway->cancelledProviderReferences)->toBe(['pi_test_fake_2']);
    // Still exactly one live hold — the first visitor's.
    expect(CheckoutHold::query()->where('cat_id', $cat->id)->count())->toBe(1);
    expect(CheckoutHold::query()->where('payment_intent_id', 'pi_test_fake_1')->exists())->toBeTrue();
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

it('refuses a checkout for a cat that already has one paid deposit, without ever creating a PaymentIntent for it', function () {
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
    // Public\DepositController::store()'s own re-check (see CLAUDE.md)
    // rejects before ever calling the gateway — no PaymentIntent wasted on
    // a reservation that was already doomed.
    expect($gateway->createPaymentIntentCalls)->toBeEmpty();
    expect(CheckoutHold::query()->count())->toBe(0);
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

it('does not notify staff when a public visitor merely starts a checkout — nothing has been paid yet', function () {
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

    // store() never calls DepositPaymentProcessor::reserve() and doesn't
    // even create a Deposit — unlike an admin-recorded reservation, a
    // public checkout isn't trustworthy enough to alert staff about until
    // it's actually paid (see CLAUDE.md).
    Notification::assertNotSentTo($activeAdmin, NewDepositCreatedNotification::class);
});

it('shows a waiting/status page for a payment intent with no deposit yet, without ever trusting the redirect alone', function () {
    refreshApplicationWithLocale('fr');

    $response = $this->get('/fr/deposits/return/pi_unknown?status=success');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/DepositReturn')
        ->where('depositStatus', 'pending')
    );
});

it('shows the real deposit status once one exists for the given payment intent', function () {
    refreshApplicationWithLocale('fr');
    $deposit = Deposit::factory()->paid()->create(['provider_reference' => 'pi_known']);

    $response = $this->get('/fr/deposits/return/pi_known?status=success');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/DepositReturn')
        ->where('depositStatus', $deposit->status->value)
    );
});
