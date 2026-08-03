<?php

use App\Models\Testimonial;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.testimonials.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.testimonials.index'));

    $response->assertForbidden();
});

it('lets an admin list testimonials', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Testimonial::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.testimonials.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Testimonials/Index')
        ->has('testimonials.data', 2)
    );
});

it('lets an admin create a testimonial', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.testimonials.store'), [
        'author_name' => 'Marie Dupont',
        'quote' => ['fr' => 'Excellent élevage', 'en' => 'Excellent breeder'],
        'rating' => 5,
        'is_published' => true,
        'order' => 1,
    ]);

    $response->assertRedirect(route('admin.testimonials.index'));
    expect(Testimonial::firstWhere('author_name', 'Marie Dupont'))->not->toBeNull();
});

it('rejects a rating outside 1-5', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.testimonials.store'), [
        'author_name' => 'Marie Dupont',
        'quote' => ['fr' => 'x', 'en' => 'y'],
        'rating' => 7,
    ]);

    $response->assertSessionHasErrors('rating');
});

it('lets an admin delete a testimonial', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $testimonial = Testimonial::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.testimonials.destroy', $testimonial));

    $response->assertRedirect(route('admin.testimonials.index'));
    expect(Testimonial::find($testimonial->id))->toBeNull();
});
