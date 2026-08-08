<?php

use App\Models\User;
use Illuminate\Notifications\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

// A bare database-only notification, deliberately not one of the app's own
// App\Notifications\* classes — this test only exercises the generic
// read/read-all endpoints, not any specific notification's payload.
function testDatabaseNotification(): Notification
{
    return new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['database'];
        }

        public function toDatabase(object $notifiable): array
        {
            return ['title' => 'Test', 'message' => 'Test notification'];
        }
    };
}

it('marks a single notification as read, scoped to the current user', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $admin->notify(testDatabaseNotification());
    $notification = $admin->notifications()->sole();

    $response = $this->actingAs($admin)->post(route('admin.notifications.read', $notification->id));

    $response->assertRedirect();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it("refuses to mark another admin's notification as read", function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $owner->assignRole('admin');
    $owner->notify(testDatabaseNotification());
    $notification = $owner->notifications()->sole();

    $otherAdmin = User::factory()->create(['email_verified_at' => now()]);
    $otherAdmin->assignRole('admin');

    $response = $this->actingAs($otherAdmin)->post(route('admin.notifications.read', $notification->id));

    $response->assertNotFound();
    expect($notification->fresh()->read_at)->toBeNull();
});

it("marks all of the current user's notifications as read", function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $admin->notify(testDatabaseNotification());
    $admin->notify(testDatabaseNotification());

    $response = $this->actingAs($admin)->post(route('admin.notifications.read-all'));

    $response->assertRedirect();
    expect($admin->unreadNotifications()->count())->toBe(0);
});
