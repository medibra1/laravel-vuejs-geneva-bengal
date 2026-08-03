<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.users.index'));

    $response->assertRedirect(route('login'));
});

it('denies a plain admin — user management is super_admin-only', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertForbidden();
});

it('lets a super_admin list admin accounts', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $otherAdmin = User::factory()->create(['email_verified_at' => now()]);
    $otherAdmin->assignRole('admin');

    $response = $this->actingAs($superAdmin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Users/Index')
        ->has('users', 2)
    );
});

it('creates an admin with a random password and sends a reset link, not a plaintext password', function () {
    Notification::fake();
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
        'role' => 'admin',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $newUser = User::firstWhere('email', 'new-admin@example.com');
    expect($newUser)->not->toBeNull();
    expect($newUser->hasRole('admin'))->toBeTrue();
    expect($newUser->is_active)->toBeTrue();
    Notification::assertSentTo($newUser, ResetPassword::class);
});

it('lets a super_admin change another admin role', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $target = User::factory()->create(['email_verified_at' => now()]);
    $target->assignRole('admin');

    $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $target), [
        'role' => 'super_admin',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect($target->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('prevents demoting the last active super_admin', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $superAdmin), [
        'role' => 'admin',
    ]);

    $response->assertRedirect();
    expect($superAdmin->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('prevents deactivating the last active super_admin', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->patch(route('admin.users.toggle-active', $superAdmin));

    $response->assertRedirect();
    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

it('lets a super_admin deactivate another admin', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $target = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $target->assignRole('admin');

    $response = $this->actingAs($superAdmin)->patch(route('admin.users.toggle-active', $target));

    $response->assertRedirect();
    expect($target->fresh()->is_active)->toBeFalse();
});

it('prevents deleting the last active super_admin', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->delete(route('admin.users.destroy', $superAdmin));

    $response->assertRedirect();
    expect(User::find($superAdmin->id))->not->toBeNull();
});

it('deletes an admin account that never acted on anything', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');
    $target = User::factory()->create(['email_verified_at' => now()]);
    $target->assignRole('admin');

    $response = $this->actingAs($superAdmin)->delete(route('admin.users.destroy', $target));

    $response->assertRedirect(route('admin.users.index'));
    expect(User::find($target->id))->toBeNull();
});

it('refuses to delete an admin account with logged activity, suggesting deactivation instead', function () {
    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('super_admin');

    // $target needs super_admin momentarily to be allowed through the
    // route middleware while performing the logged action below.
    $target = User::factory()->create(['email_verified_at' => now()]);
    $target->assignRole('super_admin');

    $thirdAdmin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $thirdAdmin->assignRole('admin');

    // Deactivating $thirdAdmin changes `is_active`, a logged attribute —
    // unlike a role change, which lives in spatie/permission's own pivot
    // table and isn't observed by LogsActivity. This makes $target the
    // causer of a real activity log entry.
    $this->actingAs($target)->patch(route('admin.users.toggle-active', $thirdAdmin));
    expect(Activity::where('causer_id', $target->id)->exists())->toBeTrue();

    $target->syncRoles(['admin']);

    $response = $this->actingAs($superAdmin)->delete(route('admin.users.destroy', $target));

    $response->assertRedirect();
    expect(User::find($target->id))->not->toBeNull();
});
