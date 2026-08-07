<?php

use App\Models\NewsletterSubscriber;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.newsletter-subscribers.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.newsletter-subscribers.index'));

    $response->assertForbidden();
});

it('lets an admin list newsletter subscribers', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    NewsletterSubscriber::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.newsletter-subscribers.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/NewsletterSubscribers/Index')
        ->has('subscribers.data', 2)
    );
});

it('lets an admin export subscribers as CSV', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    NewsletterSubscriber::factory()->create(['email' => 'fan@example.com']);

    $response = $this->actingAs($admin)->get(route('admin.newsletter-subscribers.export'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('fan@example.com');
});

it('lets an admin manually unsubscribe a subscriber', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $subscriber = NewsletterSubscriber::factory()->create();

    $response = $this->actingAs($admin)->patch(route('admin.newsletter-subscribers.toggle-unsubscribed', $subscriber));

    $response->assertRedirect();
    expect($subscriber->fresh()->isUnsubscribed())->toBeTrue();
});

it('lets an admin manually resubscribe a subscriber', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $subscriber = NewsletterSubscriber::factory()->unsubscribed()->create();

    $response = $this->actingAs($admin)->patch(route('admin.newsletter-subscribers.toggle-unsubscribed', $subscriber));

    $response->assertRedirect();
    expect($subscriber->fresh()->isUnsubscribed())->toBeFalse();
});
