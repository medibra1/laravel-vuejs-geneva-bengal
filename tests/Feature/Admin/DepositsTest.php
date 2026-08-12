<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\Owner;
use App\Models\User;
use App\Notifications\DepositConfirmedNotification;
use App\Notifications\DepositPaidNotification;
use App\Notifications\NewDepositCreatedNotification;
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

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.refund', $deposit));

    $response->assertForbidden();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
});

it('lets a super_admin refund a paid deposit', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $deposit = Deposit::factory()->paid()->create();

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.refund', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Refunded);
});

it('refuses to refund a deposit that was never paid', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $deposit = Deposit::factory()->create(['status' => DepositStatus::Pending]);

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.refund', $deposit));

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

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.refund', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
});

// --- cancel: undoes a paid deposit, releasing the cat -------------------

it('cancels a paid deposit still holding the cat en_attente, releasing it back to disponible and allowing a new reservation', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);
    $deposit = Deposit::factory()->paid()->create(['cat_id' => $cat->id]);

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.cancel', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Cancelled);
    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
    // Deposit::blocksNewReservation() only cares about a *paid* deposit —
    // now that this one is cancelled, a new reservation for the same cat
    // is no longer blocked.
    expect(Deposit::blocksNewReservation($cat->id))->toBeFalse();
});

it('cancels an already-finalized deposit, releasing an adopted cat back to disponible without touching owner_id or finalized_at', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Adopted->value);
    $owner = Owner::factory()->create();
    // startOfSecond(): the `finalized_at` column is a plain `timestamp`
    // (second precision, see the migration) — comparing against an
    // in-memory value that still carries microseconds would fail after
    // the DB round-trip strips them, regardless of whether cancel()
    // actually left the column untouched.
    $finalizedAt = now()->subDays(3)->startOfSecond();
    $deposit = Deposit::factory()->paid()->create([
        'cat_id' => $cat->id,
        'owner_id' => $owner->id,
        'finalized_at' => $finalizedAt,
    ]);

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.cancel', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Cancelled);
    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
    // The deposit keeps a historical record of what actually happened —
    // cancel() doesn't erase who it was finalized for or when.
    expect($deposit->fresh()->owner_id)->toBe($owner->id);
    expect($deposit->fresh()->finalized_at->equalTo($finalizedAt))->toBeTrue();
});

it('refuses to cancel a deposit that was never paid', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);
    $deposit = Deposit::factory()->create(['cat_id' => $cat->id, 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.cancel', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
});

it('denies a plain admin from cancelling a deposit — super_admin only', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);
    $deposit = Deposit::factory()->paid()->create(['cat_id' => $cat->id]);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.cancel', $deposit));

    $response->assertForbidden();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
});

it('redirects to password.confirm instead of cancelling without a recent confirmation', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $deposit = Deposit::factory()->paid()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.deposits.cancel', $deposit));

    $response->assertRedirect(route('password.confirm'));
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

it('does not notify the admin who just created the reservation themselves, but does notify other active staff', function () {
    Notification::fake();
    $creator = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $creator->assignRole('admin');
    $otherAdmin = User::factory()->create(['is_active' => true]);
    $otherAdmin->assignRole('admin');
    $cat = Cat::factory()->create();

    $this->actingAs($creator)->post(route('admin.deposits.store'), [
        'cat_id' => $cat->id,
        'name' => 'Jeanne Dupont',
        'email' => 'jeanne@example.com',
        'payment_method' => 'cash',
    ]);

    Notification::assertNotSentTo($creator, NewDepositCreatedNotification::class);
    Notification::assertSentTo($otherAdmin, NewDepositCreatedNotification::class);
});

it('holds the cat (en_attente) as soon as an admin creates a deposit for it', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);

    $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'cat_id' => $cat->id,
        'name' => 'Jeanne Dupont',
        'email' => 'jeanne@example.com',
        'payment_method' => 'cash',
    ]);

    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
});

it('refuses an admin deposit for a cat that already has one paid', function () {
    // Not "pending or paid": CatIsAvailableForDeposit (see its own
    // docblock) deliberately stopped blocking on a merely pending deposit
    // once Stripe moved to capture_method: manual — several visitors can
    // hold a parallel authorization for the same cat, arbitrated later at
    // capture time. Only a *paid* deposit blocks a new one now.
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    Deposit::factory()->paid()->create(['cat_id' => $cat->id]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'cat_id' => $cat->id,
        'name' => 'Second Visitor',
        'email' => 'second@example.com',
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors(['cat_id']);
    expect(Deposit::where('email', 'second@example.com')->exists())->toBeFalse();
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
        'amount' => 60000,
        'payment_method' => 'twint_manual',
        'new_owner' => [
            'first_name' => 'Jeanne',
            'last_name' => 'Dupont',
            'email' => 'jeanne.owner@example.com',
        ],
    ]);

    $owner = Owner::firstWhere('email', 'jeanne.owner@example.com');
    expect($owner)->not->toBeNull();
    $deposit = Deposit::firstWhere('owner_id', $owner->id);
    expect($deposit)->not->toBeNull();
});

it('derives the deposit contact fields from the owner created inline, instead of requiring them twice', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'amount' => 60000,
        'payment_method' => 'twint_manual',
        'new_owner' => [
            'first_name' => 'Jeanne',
            'last_name' => 'Dupont',
            'email' => 'jeanne.owner@example.com',
            'phone' => '+41 79 111 22 33',
        ],
    ]);

    $response->assertSessionDoesntHaveErrors(['name', 'email']);
    $owner = Owner::firstWhere('email', 'jeanne.owner@example.com');
    $deposit = Deposit::firstWhere('owner_id', $owner->id);
    expect($deposit->name)->toBe('Jeanne Dupont');
    expect($deposit->email)->toBe('jeanne.owner@example.com');
    expect($deposit->phone)->toBe('+41 79 111 22 33');
});

it('requires name and email when no owner is linked at all', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'amount' => 60000,
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors(['name', 'email']);
});

it('still requires name and email when linking an existing owner — the form pre-fills them, the backend does not derive them', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'amount' => 60000,
        'payment_method' => 'cash',
        'owner_id' => $owner->id,
    ]);

    $response->assertSessionHasErrors(['name', 'email']);
});

it('rejects stripe as a payment_method for an admin-recorded deposit', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'name' => 'Jeanne Dupont',
        'email' => 'jeanne@example.com',
        'amount' => 60000,
        'payment_method' => 'stripe',
    ]);

    // The "Stripe" option used to generate a payment link, but that link
    // only ever led to a status page, never a real payment form — see
    // CLAUDE.md. Admin-recorded reservations are cash/bank_transfer/
    // twint_manual only now; the public flow still uses Stripe normally.
    $response->assertSessionHasErrors(['payment_method']);
    expect(Deposit::where('email', 'jeanne@example.com')->exists())->toBeFalse();
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

it('creates a deposit with the payment method left "à définir plus tard"', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.deposits.store'), [
        'name' => 'Jeanne Dupont',
        'email' => 'jeanne@example.com',
        'amount' => 60000,
        'payment_method' => null,
    ]);

    $response->assertSessionDoesntHaveErrors(['payment_method']);
    $deposit = Deposit::firstWhere('email', 'jeanne@example.com');
    expect($deposit)->not->toBeNull();
    expect($deposit->payment_method)->toBeNull();
    // provider mirrors payment_method (see CLAUDE.md) — also null here,
    // not silently left at the column's own "stripe" default.
    expect($deposit->provider)->toBeNull();
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

it('rejects marking a deposit paid with no payment method chosen yet and none supplied — a clear validation error, not a crash', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->create(['payment_method' => null, 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.mark-paid', $deposit));

    $response->assertSessionHasErrors(['payment_method']);
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
    expect($deposit->fresh()->payment_method)->toBeNull();
});

it('resolves the payment method and marks the deposit paid when one is supplied at mark-paid time', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $deposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'payment_method' => null,
        'status' => DepositStatus::Pending,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.mark-paid', $deposit), [
        'payment_method' => 'twint_manual',
    ]);

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($deposit->fresh()->payment_method->value)->toBe('twint_manual');
    // provider mirrors payment_method here too, same as store().
    expect($deposit->fresh()->provider)->toBe('twint_manual');
    expect($deposit->fresh()->paid_at)->not->toBeNull();
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
});

it('rejects an invalid payment method supplied at mark-paid time for a deposit with none yet', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->create(['payment_method' => null, 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.mark-paid', $deposit), [
        'payment_method' => 'stripe',
    ]);

    $response->assertSessionHasErrors(['payment_method']);
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
    expect($deposit->fresh()->payment_method)->toBeNull();
});

it('ignores a payment method supplied at mark-paid time for a deposit that already has one', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->create(['payment_method' => 'cash', 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.mark-paid', $deposit), [
        'payment_method' => 'twint_manual',
    ]);

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    // Never overwritten — the deposit already knew how it was paid.
    expect($deposit->fresh()->payment_method->value)->toBe('cash');
});

// --- verify-stripe: on-demand check against Stripe, in place of waiting
// for the webhook or the daily reconciliation job -----------------------

it('marks a pending stripe deposit as paid when Stripe confirms the checkout is paid', function () {
    Notification::fake();
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = true;
    $this->app->instance(PaymentGateway::class, $gateway);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $deposit = Deposit::factory()->create([
        'cat_id' => $cat->id,
        'payment_method' => 'stripe',
        'status' => DepositStatus::Pending,
        'provider_reference' => 'cs_test_123',
    ]);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.verify-stripe', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
    expect($deposit->fresh()->paid_at)->not->toBeNull();
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
    Notification::assertSentTo($admin, DepositPaidNotification::class);
});

it('leaves a pending stripe deposit untouched when Stripe reports it as unpaid', function () {
    $gateway = new FakePaymentGateway;
    $gateway->checkoutPaidResult = false;
    $this->app->instance(PaymentGateway::class, $gateway);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->create(['payment_method' => 'stripe', 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.verify-stripe', $deposit));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

it('refuses to verify a non-stripe deposit against Stripe', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->create(['payment_method' => 'cash', 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.verify-stripe', $deposit));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

it('refuses to verify a stripe deposit that is not pending', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->paid()->create(['payment_method' => 'stripe']);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.verify-stripe', $deposit));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
});

// --- finalize: link/create the owner, mark the cat adopted ------------

it('finalizes a paid deposit with an existing owner already linked', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->paid()->create(['cat_id' => $cat->id, 'owner_id' => $owner->id]);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->finalized_at)->not->toBeNull();
    expect($cat->fresh()->status)->toBe(CatStatus::Adopted->value);
});

it('finalizes a paid deposit by linking an existing owner supplied in the request', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->paid()->create(['owner_id' => null]);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.finalize', $deposit), [
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

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.finalize', $deposit), [
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

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->finalized_at)->toBeNull();
});

it('refuses to finalize an already-finalized deposit', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->paid()->create(['owner_id' => $owner->id, 'finalized_at' => now()]);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->owner_id)->toBe($owner->id);
});

it('refuses to finalize a paid deposit with no owner and none supplied', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->paid()->create(['owner_id' => null]);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->finalized_at)->toBeNull();
});

it('finalizing a waiting-list deposit (no cat) does not touch any cat status', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->paid()->create(['cat_id' => null, 'owner_id' => $owner->id]);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect();
    expect($deposit->fresh()->finalized_at)->not->toBeNull();
});

// --- assign-cat: turning a waiting-list entry into a reservation -------

it('assigns a cat to a pending waiting-list deposit and holds it', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'chaton']);
    $cat->setStatus(CatStatus::Available->value);
    $deposit = Deposit::factory()->create(['cat_id' => null, 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.assign-cat', $deposit), [
        'cat_id' => $cat->id,
    ]);

    $response->assertRedirect();
    expect($deposit->fresh()->cat_id)->toBe($cat->id);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
});

it('does not re-send the new-reservation notification when assigning a cat to an existing waiting-list deposit', function () {
    Notification::fake();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $otherAdmin = User::factory()->create(['is_active' => true]);
    $otherAdmin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'chaton']);
    $cat->setStatus(CatStatus::Available->value);
    $deposit = Deposit::factory()->create(['cat_id' => null, 'status' => DepositStatus::Pending]);

    $this->actingAs($admin)->post(route('admin.deposits.assign-cat', $deposit), [
        'cat_id' => $cat->id,
    ]);

    Notification::assertNothingSent();
});

it('refuses to assign a cat to a deposit that already has one', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $existingCat = Cat::factory()->create();
    $newCat = Cat::factory()->create(['type' => 'chaton']);
    $deposit = Deposit::factory()->create(['cat_id' => $existingCat->id, 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.assign-cat', $deposit), [
        'cat_id' => $newCat->id,
    ]);

    $response->assertRedirect();
    expect($deposit->fresh()->cat_id)->toBe($existingCat->id);
});

it('refuses to assign a cat once the waiting-list deposit is no longer pending', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'chaton']);
    $deposit = Deposit::factory()->paid()->create(['cat_id' => null]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.assign-cat', $deposit), [
        'cat_id' => $cat->id,
    ]);

    $response->assertRedirect();
    expect($deposit->fresh()->cat_id)->toBeNull();
});

it('refuses to assign a breeder cat to a waiting-list deposit', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $breeder = Cat::factory()->create(['type' => 'reproducteur']);
    $deposit = Deposit::factory()->create(['cat_id' => null, 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.assign-cat', $deposit), [
        'cat_id' => $breeder->id,
    ]);

    $response->assertSessionHasErrors('cat_id');
    expect($deposit->fresh()->cat_id)->toBeNull();
});

it('refuses to assign a cat that already has an active reservation elsewhere', function () {
    // Same "paid, not pending, blocks" rule as the admin-deposit-creation
    // test above — CatIsAvailableForDeposit governs both endpoints.
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'chaton']);
    Deposit::factory()->paid()->create(['cat_id' => $cat->id]);
    $waitingListDeposit = Deposit::factory()->create(['cat_id' => null, 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.assign-cat', $waitingListDeposit), [
        'cat_id' => $cat->id,
    ]);

    $response->assertSessionHasErrors('cat_id');
    expect($waitingListDeposit->fresh()->cat_id)->toBeNull();
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

it('filters the deposits list to waiting-list entries only (no cat attached)', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    Deposit::factory()->create(['cat_id' => $cat->id]);
    Deposit::factory()->create(['cat_id' => null]);

    $response = $this->actingAs($admin)->get(route('admin.deposits.index', ['filter' => ['waiting_list' => 1]]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('deposits.data', 1)
        ->where('deposits.data.0.cat_id', null)
    );
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

// --- password.confirm: refund/finalize/verify-stripe all touch money or
// ownership and require a fresh password confirmation — see routes/admin.php
// and resources/js/Composables/useConfirmsPassword.ts. -------------------

it('redirects to password.confirm instead of refunding without a recent confirmation', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $deposit = Deposit::factory()->paid()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.deposits.refund', $deposit));

    $response->assertRedirect(route('password.confirm'));
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);
});

it('redirects to password.confirm instead of finalizing without a recent confirmation', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();
    $deposit = Deposit::factory()->paid()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.finalize', $deposit));

    $response->assertRedirect(route('password.confirm'));
    expect($deposit->fresh()->finalized_at)->toBeNull();
});

it('redirects to password.confirm instead of verifying against Stripe without a recent confirmation', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->create(['payment_method' => 'stripe', 'status' => DepositStatus::Pending]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.verify-stripe', $deposit));

    $response->assertRedirect(route('password.confirm'));
    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending);
});

// --- finalize-directly: bypasses the Deposit flow entirely, for an
// adoption handled fully off-system (gift, in-person sale) — super_admin
// only. See DepositPaymentProcessor::finalizeDirectly(). -----------------

it('lets a super_admin finalize an adoption directly for an available cat, linking an existing owner', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);
    $owner = Owner::factory()->create();

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.cats.finalize-directly'), [
        'cat_id' => $cat->id,
        'owner_id' => $owner->id,
    ]);

    $response->assertRedirect();
    expect($cat->fresh()->status)->toBe(CatStatus::Adopted->value);
    $deposit = Deposit::firstWhere('cat_id', $cat->id);
    expect($deposit)->not->toBeNull();
    expect($deposit->owner_id)->toBe($owner->id);
    expect($deposit->amount)->toBe(0);
    expect($deposit->currency)->toBe('CHF');
    expect($deposit->status)->toBe(DepositStatus::Paid);
    expect($deposit->payment_method)->toBeNull();
    expect($deposit->provider)->toBe('manual_no_deposit');
    expect($deposit->paid_at)->not->toBeNull();
    expect($deposit->finalized_at)->not->toBeNull();
    expect($deposit->name)->toBe(trim("{$owner->first_name} {$owner->last_name}"));
    expect($deposit->email)->toBe($owner->email);
});

it('lets a super_admin finalize an adoption directly for a pending cat, creating a new owner inline', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.cats.finalize-directly'), [
        'cat_id' => $cat->id,
        'new_owner' => [
            'first_name' => 'Jeanne',
            'last_name' => 'Dupont',
            'email' => 'jeanne.direct@example.com',
        ],
    ]);

    $response->assertRedirect();
    expect($cat->fresh()->status)->toBe(CatStatus::Adopted->value);
    $owner = Owner::firstWhere('email', 'jeanne.direct@example.com');
    expect($owner)->not->toBeNull();
    $deposit = Deposit::firstWhere('cat_id', $cat->id);
    expect($deposit->owner_id)->toBe($owner->id);
});

it('denies a plain admin from finalizing an adoption directly — super_admin only', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);
    $owner = Owner::factory()->create();

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.cats.finalize-directly'), [
        'cat_id' => $cat->id,
        'owner_id' => $owner->id,
    ]);

    $response->assertForbidden();
    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
    expect(Deposit::where('cat_id', $cat->id)->exists())->toBeFalse();
});

it('refuses to finalize an already-adopted cat directly', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Adopted->value);
    $owner = Owner::factory()->create();

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.cats.finalize-directly'), [
        'cat_id' => $cat->id,
        'owner_id' => $owner->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(Deposit::where('cat_id', $cat->id)->exists())->toBeFalse();
});

it('requires either an existing owner or a new one to finalize directly', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);

    $response = $this->actingAs($superAdmin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.cats.finalize-directly'), [
        'cat_id' => $cat->id,
    ]);

    $response->assertSessionHasErrors(['owner_id', 'new_owner']);
    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
});

it('redirects to password.confirm instead of finalizing directly without a recent confirmation', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);
    $owner = Owner::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.cats.finalize-directly'), [
        'cat_id' => $cat->id,
        'owner_id' => $owner->id,
    ]);

    $response->assertRedirect(route('password.confirm'));
    expect($cat->fresh()->status)->toBe(CatStatus::Available->value);
});

it('does not require a fresh confirmation for mark-paid or assign-cat — those stay behind role middleware only', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'chaton']);
    $deposit = Deposit::factory()->create(['payment_method' => 'cash', 'status' => DepositStatus::Pending, 'cat_id' => null]);

    $response = $this->actingAs($admin)->post(route('admin.deposits.mark-paid', $deposit));
    $response->assertRedirect();
    expect($deposit->fresh()->status)->toBe(DepositStatus::Paid);

    $waitingListDeposit = Deposit::factory()->create(['cat_id' => null, 'status' => DepositStatus::Pending]);
    $response = $this->actingAs($admin)->post(route('admin.deposits.assign-cat', $waitingListDeposit), ['cat_id' => $cat->id]);
    $response->assertRedirect();
    expect($waitingListDeposit->fresh()->cat_id)->toBe($cat->id);
});
