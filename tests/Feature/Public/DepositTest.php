<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
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

it('creates a pending deposit and renders the integrated checkout page with a PaymentIntent client secret', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    SiteSetting::set('deposit_amount', 50000);

    $response = $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'phone' => '+41 79 000 00 00',
    ]);

    $deposit = Deposit::sole();
    expect($deposit->status)->toBe(DepositStatus::Pending);
    expect($deposit->amount)->toBe(50000);
    expect($deposit->currency)->toBe('CHF');
    expect($deposit->provider_reference)->toBe('pi_test_fake_'.$deposit->id);

    // No more cross-origin redirect to a Stripe-hosted page — the
    // PaymentIntent is confirmed client-side, on this same response, via
    // a Stripe.js Payment Element mounted with clientSecret (see CLAUDE.md).
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/DepositPay')
        ->where('depositId', $deposit->id)
        ->where('clientSecret', 'pi_test_fake_'.$deposit->id.'_secret_test')
        // Forced to this fixed fake value in tests/bootstrap.php.
        ->where('stripePublishableKey', 'pk_test_fake_key_for_test_suite')
        ->where('catName', null)
        ->where('amount', 50000)
        ->where('currency', 'CHF')
    );
});

it('includes the cat name in the checkout page props when the deposit is for a specific cat', function () {
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

it('links a deposit to a specific cat when one is given', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $cat = Cat::factory()->create();

    $this->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'cat_id' => $cat->id,
    ]);

    expect(Deposit::sole()->cat_id)->toBe($cat->id);
});

it('leaves the cat disponible when the deposit is created — it is only held once payment is actually confirmed', function () {
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

    // A pending deposit no longer blocks another one anyway (see
    // Deposit::blocksNewReservation()), so holding the cat this early no
    // longer protects anything — it would just show the cat as
    // unavailable to every other visitor while this one is still
    // mid-checkout. DepositPaymentProcessor::confirmPaid() (via
    // markPaid()) is what actually reserves it, exactly once paid — see
    // the "moves the linked cat to en_attente" test in
    // StripeWebhookTest.php for that half of the flow.
    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
});

it('allows a new deposit for a cat that already has a pending (not yet paid) deposit — only a paid deposit blocks a reservation', function () {
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
    expect(Deposit::where('email', 'second@example.com')->exists())->toBeTrue();
});

it('refuses a new deposit for a cat that already has one paid, without ever creating a PaymentIntent for it', function () {
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
    expect(Deposit::where('email', 'second@example.com')->exists())->toBeFalse();
    // Public\DepositController::store()'s own re-check (see CLAUDE.md)
    // rejects before ever calling the gateway — no PaymentIntent wasted on
    // a reservation that was already doomed.
    expect($gateway->createPaymentIntentDepositIds)->toBeEmpty();
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

it('does not notify staff when a public visitor merely creates a deposit — nothing has been paid yet', function () {
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

    // store() deliberately doesn't call
    // DepositPaymentProcessor::reserve() (see its own docblock) — unlike
    // an admin-recorded reservation, a public deposit isn't trustworthy
    // enough to alert staff about until it's actually paid, and nothing
    // currently re-sends this notification once it is (see CLAUDE.md).
    Notification::assertNotSentTo($activeAdmin, NewDepositCreatedNotification::class);
});

it('shows a waiting/status page without ever trusting the redirect alone', function () {
    refreshApplicationWithLocale('fr');
    $deposit = Deposit::factory()->create();

    $response = $this->get("/fr/deposits/{$deposit->id}?status=success");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/DepositReturn')
        ->where('depositStatus', $deposit->status->value)
    );
});
