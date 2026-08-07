<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\Owner;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Doubles\FakePaymentGateway;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
    $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
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
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $deposit = Deposit::factory()->paid()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.deposits.refund', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Refunded);
});

it('refuses to refund a deposit that was never paid', function () {
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

// --- create/store: manual reservations -------------------------------

it('lets a plain admin create a cash deposit with no owner yet', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'cat_id' => $cat->id,
        'name' => 'Jeanne Dupont',
        'email' => 'jeanne@example.com',
        'amount' => 60000,
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect(route('admin.deposits.index'));
    $deposit = Deposit::firstWhere('email', 'jeanne@example.com');
    expect($deposit)->not->toBeNull();
    expect($deposit->payment_method->value)->toBe('cash');
    expect($deposit->provider)->toBe('cash');
    expect($deposit->status)->toBe(DepositStatus::Pending);
    expect($deposit->owner_id)->toBeNull();
    expect($deposit->created_by)->toBe($admin->id);
});

it('links an existing owner when creating a deposit', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();

    $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'name' => 'Jeanne Dupont',
        'email' => 'jeanne@example.com',
        'amount' => 60000,
        'payment_method' => 'bank_transfer',
        'owner_id' => $owner->id,
    ]);

    $deposit = Deposit::firstWhere('email', 'jeanne@example.com');
    expect($deposit->owner_id)->toBe($owner->id);
});

it('creates a new owner inline when requested at deposit creation', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'name' => 'Jeanne Dupont',
        'email' => 'jeanne@example.com',
        'amount' => 60000,
        'payment_method' => 'twint_manual',
        'new_owner' => [
            'first_name' => 'Jeanne',
            'last_name' => 'Dupont',
            'email' => 'jeanne.owner@example.com',
        ],
    ]);

    $deposit = Deposit::firstWhere('email', 'jeanne@example.com');
    $owner = Owner::firstWhere('email', 'jeanne.owner@example.com');
    expect($owner)->not->toBeNull();
    expect($deposit->owner_id)->toBe($owner->id);
});

it('generates a Stripe payment link when payment_method is stripe', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'name' => 'Jeanne Dupont',
        'email' => 'jeanne@example.com',
        'amount' => 60000,
        'payment_method' => 'stripe',
    ]);

    $deposit = Deposit::firstWhere('email', 'jeanne@example.com');
    expect($deposit->provider_reference)->not->toBeNull();
    expect($deposit->payment_link_url)->not->toBeNull();
});

it('does not generate a payment link for a manual payment method', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'name' => 'Jeanne Dupont',
        'email' => 'jeanne@example.com',
        'amount' => 60000,
        'payment_method' => 'cash',
    ]);

    $deposit = Deposit::firstWhere('email', 'jeanne@example.com');
    expect($deposit->payment_link_url)->toBeNull();
});

// --- mark-paid: cash/bank_transfer/twint_manual -----------------------

it('lets a plain admin mark a cash deposit as paid', function () {
    Notification::fake();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $deposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'payment_method' => 'cash',
        'status' => DepositStatus::Pending,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.mark-paid', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($deposit->fresh()->paid_at)->not->toBeNull();
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
});

it('refuses to manually mark a stripe deposit as paid', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->create(['payment_method' => 'stripe', 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.mark-paid', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

it('refuses to mark an already-paid deposit as paid again', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->paid()->create(['payment_method' => 'cash']);

    $response = $this->actingAs($admin)->post(route('admin.deposits.mark-paid', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
});

// --- finalize: link/create the owner, mark the cat adopted ------------

it('finalizes a paid deposit with an existing owner already linked', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->paid()->create(['cat_id' => $cat->id, 'owner_id' => $owner->id]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->finalized_at)->not->toBeNull();
    expect($cat->fresh()->status)->toBe(CatStatus::Adopted->value);
});

it('finalizes a paid deposit by linking an existing owner supplied in the request', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->paid()->create(['owner_id' => null]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.finalize', $deposit), [
        'owner_id' => $owner->id,
    ]);

    $response->assertRedirect();
    expect($deposit->fresh()->owner_id)->toBe($owner->id);
    expect($deposit->fresh()->finalized_at)->not->toBeNull();
});

it('finalizes a paid deposit by creating a new owner inline', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->paid()->create(['owner_id' => null]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.finalize', $deposit), [
        'new_owner' => [
            'first_name' => 'Jeanne',
            'last_name' => 'Dupont',
            'email' => 'jeanne.finalize@example.com',
        ],
    ]);

    $response->assertRedirect();
    $owner = Owner::firstWhere('email', 'jeanne.finalize@example.com');
    expect($owner)->not->toBeNull();
    expect($deposit->fresh()->owner_id)->toBe($owner->id);
});

it('refuses to finalize a deposit that is not paid', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->create(['status' => DepositStatus::Pending, 'owner_id' => $owner->id]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->finalized_at)->toBeNull();
});

it('refuses to finalize an already-finalized deposit', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->paid()->create(['owner_id' => $owner->id, 'finalized_at' => now()]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->owner_id)->toBe($owner->id);
});

it('refuses to finalize a paid deposit with no owner and none supplied', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->paid()->create(['owner_id' => null]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->finalized_at)->toBeNull();
});

it('finalizing a waiting-list deposit (no cat) does not touch any cat status', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->paid()->create(['cat_id' => null, 'owner_id' => $owner->id]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->finalized_at)->not->toBeNull();
});

// --- index filters ------------------------------------------------------

it('filters the deposits list by status', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Deposit::factory()->paid()->create();
    Deposit::factory()->create(['status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->get(route('admin.deposits.index', ['filter' => ['status' => 'paid']]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('deposits.data', 1));
});

it('filters the deposits list by cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    Deposit::factory()->create(['cat_id' => $cat->id]);
    Deposit::factory()->create(['cat_id' => null]);

    $response = $this->actingAs($admin)->get(route('admin.deposits.index', ['filter' => ['cat_id' => $cat->id]]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('deposits.data', 1));
});

it('filters the deposits list by period', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Deposit::factory()->create(['created_at' => now()->subMonths(2)]);
    Deposit::factory()->create(['created_at' => now()]);

    $response = $this->actingAs($admin)->get(route('admin.deposits.index', [
        'filter' => ['from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString()],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('deposits.data', 1));
});
