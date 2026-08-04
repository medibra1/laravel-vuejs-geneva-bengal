<?php

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Spatie\Permission\Models\Role;
use Tests\Doubles\FakePaymentGateway;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.deposits.index'));

    $response->assertRedirect(route('login'));
});

it('lets a plain admin view the deposits list', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Deposit::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.deposits.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Deposits/Index')
        ->has('deposits.data', 2)
    );
});

it('denies a plain admin from refunding a deposit — super_admin only', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->paid()->create();

    $response = $this->actingAs($admin)->post(route('admin.deposits.refund', $deposit));

    $response->assertForbidden();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
});

it('lets a super_admin refund a paid deposit', function () {
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $deposit = Deposit::factory()->paid()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.deposits.refund', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Refunded);
});

it('refuses to refund a deposit that was never paid', function () {
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $deposit = Deposit::factory()->create(['status' => DepositStatus::Pending]);

    $response = $this->actingAs($superAdmin)->post(route('admin.deposits.refund', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

it('surfaces a gateway-level refund failure instead of marking the deposit refunded', function () {
    $gateway = new FakePaymentGateway;
    $gateway->refundResult = false;
    $this->app->instance(PaymentGateway::class, $gateway);
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $deposit = Deposit::factory()->paid()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.deposits.refund', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
});
