<?php

use App\Enums\DepositStatus;
use App\Jobs\ReconcilePendingDeposits;
use App\Models\Deposit;
use App\Notifications\DepositConfirmedNotification;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Notification;
use Tests\Doubles\FakePaymentGateway;

it('marks paid a pending deposit the gateway reports as paid', function () {
    Notification::fake();
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = true;
    $this->app->instance(PaymentGateway::class, $gateway);

    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'cs_test_reconcile',
        'created_at' => now()->subHours(2),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
});

it('leaves a deposit alone if the gateway still reports it unpaid', function () {
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = false;
    $this->app->instance(PaymentGateway::class, $gateway);

    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'provider_reference' => 'cs_test_still_pending',
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
        'provider_reference' => 'cs_test_fresh',
        'created_at' => now()->subMinutes(10),
    ]);

    (new ReconcilePendingDeposits)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

it('skips a deposit with no checkout session to poll', function () {
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
