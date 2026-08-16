<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.activity-log.index'));

    $response->assertRedirect(route('login'));
});

it('denies a plain admin — the activity log is super_admin-only', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.activity-log.index'));

    $response->assertForbidden();
});

it('lets a super_admin list activity log entries', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $target = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $target->assignRole('admin');

    // User::factory()->create() is itself logged (a "created" entry per
    // user above), plus toggling is_active below adds an "updated" entry
    // — see User::getActivitylogOptions().
    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->patch(route('admin.users.toggle-active', $target));

    $response = $this->actingAs($superAdmin)->get(route('admin.activity-log.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/ActivityLog/Index')
        ->has('activities.data', 3)
    );

    expect(Activity::where('event', 'updated')->where('causer_id', $superAdmin->id)->exists())->toBeTrue();
});

it('filters activity log entries by causer', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $otherAdmin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $otherAdmin->assignRole('admin');
    $target = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $target->assignRole('admin');

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->patch(route('admin.users.toggle-active', $target));

    $response = $this->actingAs($superAdmin)->get(route('admin.activity-log.index', [
        'filter' => ['causer_id' => $otherAdmin->id],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/ActivityLog/Index')
        ->has('activities.data', 0)
    );
});

it('filters activity log entries by event', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $target = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $target->assignRole('admin');

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->patch(route('admin.users.toggle-active', $target));

    $response = $this->actingAs($superAdmin)->get(route('admin.activity-log.index', [
        'filter' => ['event' => 'deleted'],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/ActivityLog/Index')
        ->has('activities.data', 0)
    );

    expect(Activity::where('event', 'updated')->exists())->toBeTrue();
});
