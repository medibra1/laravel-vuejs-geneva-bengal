<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Jobs\ReconcilePendingDeposits;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\User;
use App\Notifications\DepositConfirmedNotification;
use App\Notifications\DepositPaidNotification;
use App\Notifications\DepositUnavailableNotification;
use App\Notifications\StripeReconciliationIssueNotification;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Doubles\FakePaymentGateway;

beforeEach(function () {
    // The 'expired'/'error' branches below, and the "lost the race" path
    // that markPaid() can now take, all notify active staff — see
    // NotifiesStaff — which looks these roles up even on the tests that
    // never hit any of them.
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('marks paid and captures the PaymentIntent for a pending deposit the gateway reports as paid', function () {
    Notification::fake();
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = true;
    $this->app->instance(PaymentGateway::class, $gateway);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_reconcile',
        'created_at' => now()->subHours(2),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($gateway->capturedDepositIds)->toBe([$deposit->id]);
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
    Notification::assertSentTo($admin, DepositPaidNotification::class);
});

it('leaves a deposit alone if the gateway still reports it unpaid', function () {
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = false;
    $this->app->instance(PaymentGateway::class, $gateway);

    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_still_pending',
        'created_at' => now()->subHours(2),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

it('never touches a deposit still inside the one-hour grace window', function () {
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = true;
    $this->app->instance(PaymentGateway::class, $gateway);

    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_fresh',
        'created_at' => now()->subMinutes(10),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

it('releases the cat and cancels a deposit whose PaymentIntent authorization is old enough to be considered abandoned', function () {
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = false;
    $this->app->instance(PaymentGateway::class, $gateway);

    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);
    $deposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_abandoned',
        'created_at' => now()->subHours(25),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->status)->toBe(DepositStatus::Cancelled);
    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
});

it('does not expire a pending deposit still inside the abandonment window', function () {
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = false;
    $this->app->instance(PaymentGateway::class, $gateway);

    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);
    $deposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_still_active',
        'created_at' => now()->subHours(2),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
});

it('notifies active staff with reason expired and keeps processing the rest of the batch', function () {
    Notification::fake();
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = false;
    $this->app->instance(PaymentGateway::class, $gateway);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $expiredCat = Cat::factory()->create();
    $expiredCat->setStatus(CatStatus::Pending->value);
    $expiredDeposit = Deposit::factory()->create([
        'cat_id' => $expiredCat->id,
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_expired_a',
        'created_at' => now()->subHours(25),
    ]);
    $secondExpiredDeposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_expired_b',
        'created_at' => now()->subHours(30),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($expiredDeposit->fresh()->status)->toBe(DepositStatus::Cancelled);
    expect($secondExpiredDeposit->fresh()->status)->toBe(DepositStatus::Cancelled);
    Notification::assertSentTo(
        $admin,
        StripeReconciliationIssueNotification::class,
        fn (StripeReconciliationIssueNotification $notification) => $notification->reason === 'expired'
            && $notification->deposit->is($expiredDeposit),
    );
    Notification::assertSentTo(
        $admin,
        StripeReconciliationIssueNotification::class,
        fn (StripeReconciliationIssueNotification $notification) => $notification->reason === 'expired'
            && $notification->deposit->is($secondExpiredDeposit),
    );
});

it('notifies active staff with reason error and keeps processing the rest of the batch when the gateway blows up', function () {
    Notification::fake();
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidException = new RuntimeException('Stripe API unreachable');
    $this->app->instance(PaymentGateway::class, $gateway);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $firstDeposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_error_a',
        'created_at' => now()->subHours(2),
    ]);
    $secondDeposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_error_b',
        'created_at' => now()->subHours(2),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    // Untouched — a failed *check* is not a failed payment, see
    // ReconcilePendingDeposits' try/catch.
    expect($firstDeposit->fresh()->status)->toBe(DepositStatus::Pending);
    expect($secondDeposit->fresh()->status)->toBe(DepositStatus::Pending);
    Notification::assertSentTo(
        $admin,
        StripeReconciliationIssueNotification::class,
        fn (StripeReconciliationIssueNotification $notification) => $notification->reason === 'error'
            && $notification->errorMessage === 'Stripe API unreachable'
            && $notification->deposit->is($firstDeposit),
    );
    Notification::assertSentTo(
        $admin,
        StripeReconciliationIssueNotification::class,
        fn (StripeReconciliationIssueNotification $notification) => $notification->reason === 'error'
            && $notification->deposit->is($secondDeposit),
    );
});

it('skips a deposit with no PaymentIntent to poll', function () {
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = true;
    $this->app->instance(PaymentGateway::class, $gateway);

    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => null,
        'created_at' => now()->subHours(2),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

it('cancels the losing deposit\'s PaymentIntent instead of capturing it when reconciliation finds another deposit for the same cat already paid', function () {
    Notification::fake();
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = true;
    $this->app->instance(PaymentGateway::class, $gateway);

    $cat = Cat::factory()->create();
    $winningDeposit = Deposit::factory()->paid()->create([
        'cat_id' => $cat->id,
        'provider_reference' => 'pi_test_winner',
    ]);
    $losingDeposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'status' => DepositStatus::Pending,
        'provider_reference' => 'pi_test_loser',
        'created_at' => now()->subHours(2),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($losingDeposit->fresh()->status)->toBe(DepositStatus::Unavailable);
    expect($winningDeposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($gateway->cancelledDepositIds)->toBe([$losingDeposit->id]);
    expect($gateway->capturedDepositIds)->toBeEmpty();
    Notification::assertSentOnDemand(DepositUnavailableNotification::class);
});
