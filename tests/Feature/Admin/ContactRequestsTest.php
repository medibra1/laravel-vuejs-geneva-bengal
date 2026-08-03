<?php

use App\Enums\ContactStatus;
use App\Models\ContactRequest;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.contact-requests.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.contact-requests.index'));

    $response->assertForbidden();
});

it('lets an admin list contact requests', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    ContactRequest::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.contact-requests.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/ContactRequests/Index')
        ->has('contactRequests.data', 2)
    );
});

it('lets an admin change a contact request status', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $contactRequest = ContactRequest::factory()->create(['status' => ContactStatus::New]);

    $response = $this->actingAs($admin)->put(route('admin.contact-requests.update', $contactRequest), [
        'status' => ContactStatus::Processed->value,
    ]);

    $response->assertRedirect(route('admin.contact-requests.index'));
    expect($contactRequest->fresh()->status)->toBe(ContactStatus::Processed);
});

it('lets an admin delete a contact request', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $contactRequest = ContactRequest::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.contact-requests.destroy', $contactRequest));

    $response->assertRedirect(route('admin.contact-requests.index'));
    expect(ContactRequest::find($contactRequest->id))->toBeNull();
});
