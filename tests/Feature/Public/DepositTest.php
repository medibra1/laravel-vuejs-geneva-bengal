<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\SiteSetting;
use App\Services\Payments\PaymentGateway;
use Spatie\Honeypot\Honeypot;
use Tests\Doubles\FakePaymentGateway;

// PaymentGateway is (re)bound inside each test, after
// refreshApplicationWithLocale() — that helper rebuilds the whole
// application container, which would otherwise discard a binding made in
// beforeEach().

it('creates a pending deposit and redirects to the Stripe checkout URL', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    SiteSetting::set('deposit_amount', 50000);

    // The real reservation button submits via Inertia's useForm(), which
    // always sends this header — without it, Inertia::location() falls
    // back to a plain 302 (see ResponseFactory::location()), which is
    // only correct for a non-Inertia caller.
    $response = $this->withHeader('X-Inertia', 'true')->post('/fr/deposits', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'phone' => '+41 79 000 00 00',
    ]);

    $deposit = Deposit::sole();
    expect($deposit->status)->toBe(DepositStatus::Pending);
    expect($deposit->amount)->toBe(50000);
    expect($deposit->currency)->toBe('CHF');
    expect($deposit->provider_reference)->toBe('cs_test_fake_'.$deposit->id);

    // Inertia::location() replies with a 409 + a header carrying the
    // target URL — the client does a hard browser visit instead of
    // trying to follow a cross-origin redirect as an XHR.
    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/fake/'.$deposit->id);
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

it('holds the cat (en_attente) as soon as the deposit is created, before any payment is confirmed', function () {
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

    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
});

it('refuses a new deposit for a cat that already has one pending', function () {
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

    $response->assertSessionHasErrors(['cat_id']);
    expect(Deposit::where('email', 'second@example.com')->exists())->toBeFalse();
});

it('refuses a new deposit for a cat that already has one paid', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $cat = Cat::factory()->create();
    Deposit::factory()->paid()->create(['cat_id' => $cat->id]);

    $response = $this->post('/fr/deposits', [
        'name' => 'Second Visitor',
        'email' => 'second@example.com',
        'cat_id' => $cat->id,
    ]);

    $response->assertSessionHasErrors(['cat_id']);
});

it('allows a new deposit for a cat whose only pending deposit is old enough to be considered abandoned', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $cat = Cat::factory()->create();
    Deposit::factory()->create([
        'cat_id' => $cat->id,
        'status' => DepositStatus::Pending,
        'created_at' => now()->subHours(25),
    ]);

    $response = $this->post('/fr/deposits', [
        'name' => 'Second Visitor',
        'email' => 'second@example.com',
        'cat_id' => $cat->id,
    ]);

    $response->assertSessionDoesntHaveErrors(['cat_id']);
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
