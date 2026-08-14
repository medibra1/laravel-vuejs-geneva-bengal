<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Jobs\ReconcileCheckouts;
use App\Models\Cat;
use App\Models\CheckoutHold;
use App\Models\Deposit;
use App\Models\User;
use App\Notifications\DepositConfirmationUndeliveredNotification;
use App\Notifications\DepositConfirmedNotification;
use App\Notifications\DepositPaidNotification;
use App\Notifications\DepositUnavailableNotification;
use App\Notifications\StripeReconciliationIssueNotification;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentWebhookResult;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Doubles\FailingMailChannel;
use Tests\Doubles\FakePaymentGateway;

beforeEach(function () {
    // Every notification this job can send targets active staff — see
    // NotifiesStaff — which looks these roles up even on the tests that
    // never hit any of them.
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

function expiredCheckoutHold(?int $catId, string $paymentIntentId): CheckoutHold
{
    return CheckoutHold::query()->create([
        'cat_id' => $catId,
        'payment_intent_id' => $paymentIntentId,
        'expires_at' => now()->subMinute(),
        'hard_expires_at' => now()->addMinutes(10),
    ]);
}

// --- Volet 1: expired CheckoutHolds ---------------------------------------

it('never touches a checkout hold still within its TTL', function () {
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    $cat = Cat::factory()->create();
    CheckoutHold::query()->create([
        'cat_id' => $cat->id,
        'payment_intent_id' => 'pi_fresh',
        'expires_at' => now()->addMinutes(2),
        'hard_expires_at' => now()->addMinutes(10),
    ]);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect(CheckoutHold::query()->where('payment_intent_id', 'pi_fresh')->exists())->toBeTrue();
    expect(Deposit::count())->toBe(0);
});

it('replays a lost webhook and creates the Deposit when Stripe reports the PaymentIntent as paid', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);
    $hold = expiredCheckoutHold($cat->id, 'pi_lost_webhook');
    $gateway = new FakePaymentGateway;
    $gateway->checkoutDataResults['pi_lost_webhook'] = new PaymentWebhookResult(
        handled: true,
        paymentIntentId: 'pi_lost_webhook',
        metadata: ['cat_id' => (string) $cat->id, 'name' => 'Marie Dupont', 'email' => 'marie@example.com', 'locale' => 'fr'],
        amount: 50000,
        currency: 'CHF',
    );
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    $deposit = Deposit::sole();
    expect($deposit->status)->toBe(DepositStatus::Paid);
    expect($deposit->provider_reference)->toBe('pi_lost_webhook');
    expect($deposit->cat_id)->toBe($cat->id);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
    expect(CheckoutHold::query()->whereKey($hold->id)->exists())->toBeFalse();
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
    Notification::assertSentTo($admin, DepositPaidNotification::class);
});

it('does not duplicate the deposit when a webhook actually did arrive between the hold expiring and this job running', function () {
    Notification::fake();
    $cat = Cat::factory()->create();
    $hold = expiredCheckoutHold($cat->id, 'pi_already_processed');
    Deposit::factory()->paid()->create(['cat_id' => $cat->id, 'provider_reference' => 'pi_already_processed']);
    $gateway = new FakePaymentGateway;
    $gateway->checkoutDataResults['pi_already_processed'] = new PaymentWebhookResult(
        handled: true,
        paymentIntentId: 'pi_already_processed',
        metadata: ['name' => 'Marie Dupont', 'email' => 'marie@example.com'],
        amount: 50000,
        currency: 'CHF',
    );
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect(Deposit::where('provider_reference', 'pi_already_processed')->count())->toBe(1);
    expect(CheckoutHold::query()->whereKey($hold->id)->exists())->toBeTrue();
});

it('deletes the checkout hold, freeing the cat, when Stripe reports the PaymentIntent as never paid', function () {
    $cat = Cat::factory()->create();
    $hold = expiredCheckoutHold($cat->id, 'pi_never_paid');
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect(CheckoutHold::query()->whereKey($hold->id)->exists())->toBeFalse();
    expect(Deposit::count())->toBe(0);
});

it('notifies staff and keeps processing the rest of the batch when Stripe itself errors out', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $firstCat = Cat::factory()->create();
    $firstHold = expiredCheckoutHold($firstCat->id, 'pi_error_a');
    $secondCat = Cat::factory()->create();
    $secondHold = expiredCheckoutHold($secondCat->id, 'pi_error_b');
    $gateway = new FakePaymentGateway;
    $gateway->retrieveCheckoutDataException = new RuntimeException('Stripe API unreachable');
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    // Untouched — a failed check is not a resolved outcome either way.
    expect(CheckoutHold::query()->whereKey($firstHold->id)->exists())->toBeTrue();
    expect(CheckoutHold::query()->whereKey($secondHold->id)->exists())->toBeTrue();
    Notification::assertSentTo(
        $admin,
        StripeReconciliationIssueNotification::class,
        fn (StripeReconciliationIssueNotification $notification) => $notification->errorMessage === 'Stripe API unreachable'
            && $notification->checkoutHold->is($firstHold),
    );
    Notification::assertSentTo(
        $admin,
        StripeReconciliationIssueNotification::class,
        fn (StripeReconciliationIssueNotification $notification) => $notification->checkoutHold->is($secondHold),
    );
});

it('cancels a losing card PaymentIntent replayed via reconciliation, creating no deposit for the loser', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);
    Deposit::factory()->paid()->create(['cat_id' => $cat->id, 'provider_reference' => 'pi_winner']);
    expiredCheckoutHold($cat->id, 'pi_loser');
    $gateway = new FakePaymentGateway;
    $gateway->checkoutDataResults['pi_loser'] = new PaymentWebhookResult(
        handled: true,
        paymentIntentId: 'pi_loser',
        metadata: ['cat_id' => (string) $cat->id, 'name' => 'Second Visitor', 'email' => 'second@example.com'],
        amount: 50000,
        currency: 'CHF',
    );
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect(Deposit::where('email', 'second@example.com')->exists())->toBeFalse();
    expect(Deposit::count())->toBe(1);
    expect($gateway->cancelledProviderReferences)->toBe(['pi_loser']);
    Notification::assertSentOnDemand(DepositUnavailableNotification::class);
});

// --- Volet 2: failed confirmation emails -----------------------------------

it('retries a paid deposit whose confirmation email never went out, and marks it sent on success', function () {
    Notification::fake();
    $deposit = Deposit::factory()->paid()->create([
        'confirmation_sent_at' => null,
        'confirmation_attempts' => 2,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_sent_at)->not->toBeNull();
    expect($deposit->fresh()->confirmation_attempts)->toBe(3);
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
});

it('never retries a deposit whose confirmation already went out', function () {
    Notification::fake();
    $deposit = Deposit::factory()->paid()->create([
        'confirmation_sent_at' => now()->subHour(),
        'confirmation_attempts' => 1,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_attempts)->toBe(1);
    Notification::assertNothingSent();
});

it('never retries a deposit that is not paid', function () {
    Notification::fake();
    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'confirmation_sent_at' => null,
        'confirmation_attempts' => 0,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_attempts)->toBe(0);
    Notification::assertNothingSent();
});

it('stops retrying and notifies staff once a confirmation email has failed 5 times', function () {
    // Not Notification::fake() here: FailingMailChannel must actually run
    // for real (this test's whole point is to make
    // sendClientConfirmation() genuinely fail) — a full fake would
    // intercept the send before it ever reaches the channel and make it
    // trivially "succeed". DepositConfirmationUndeliveredNotification's
    // own send is verified via its real `database` write instead of
    // Notification::assertSentTo().
    $this->app->bind(MailChannel::class, FailingMailChannel::class);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->paid()->create([
        'confirmation_sent_at' => null,
        'confirmation_attempts' => 4,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_attempts)->toBe(5);
    expect($deposit->fresh()->confirmation_sent_at)->toBeNull();
    expect($admin->fresh()->notifications()->where('type', DepositConfirmationUndeliveredNotification::class)->exists())->toBeTrue();
});

it('does not pick up a deposit that already reached the max attempts on a previous run', function () {
    Notification::fake();
    $deposit = Deposit::factory()->paid()->create([
        'confirmation_sent_at' => null,
        'confirmation_attempts' => 5,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_attempts)->toBe(5);
    Notification::assertNothingSent();
});
