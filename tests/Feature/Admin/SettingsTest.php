<?php

use App\Models\SiteSetting;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.settings.edit'));

    $response->assertRedirect(route('login'));
});

it('denies a plain admin — settings is super_admin-only', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.settings.edit'));

    $response->assertForbidden();
});

it('lets a super_admin view the settings form', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->get(route('admin.settings.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Admin/Settings/Edit'));
});

it('lets a super_admin update settings', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
        'address' => '1209 Genève, Suisse',
        'deposit_amount' => 50000,
        'price_range_min' => 150000,
        'price_range_max' => 350000,
    ]);

    $response->assertRedirect(route('admin.settings.edit'));
    expect(SiteSetting::get('address'))->toBe('1209 Genève, Suisse');
    expect(SiteSetting::get('deposit_amount'))->toBe(50000);
});

it('rejects a price range where the max is below the min', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
        'price_range_min' => 300000,
        'price_range_max' => 100000,
    ]);

    $response->assertSessionHasErrors('price_range_max');
});
