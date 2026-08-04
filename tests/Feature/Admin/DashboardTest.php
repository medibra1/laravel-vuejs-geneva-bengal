<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

it('lets a plain admin view the dashboard — not super_admin-only', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->has('stats.kpis')
        ->has('stats.charts')
        ->has('period.from')
        ->has('period.to')
    );
});

it('defaults to the current month when no period is given', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('period.from', now()->startOfMonth()->toDateString())
    );
});

it('returns JSON stats for a custom period without a full page reload', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->getJson(route('dashboard.stats', [
        'from' => '2026-01-01',
        'to' => '2026-01-31',
    ]));

    $response->assertOk();
    $response->assertJsonStructure([
        'stats' => ['kpis', 'charts'],
        'period' => ['from', 'to'],
    ]);
    $response->assertJsonPath('period.from', '2026-01-01');
    $response->assertJsonPath('period.to', '2026-01-31');
});

it('rejects a malformed period instead of erroring', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->getJson(route('dashboard.stats', ['from' => 'not-a-date']));

    $response->assertStatus(422);
});
