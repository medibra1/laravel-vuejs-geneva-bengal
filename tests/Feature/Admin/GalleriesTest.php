<?php

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
    Storage::fake('public');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.galleries.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.galleries.index'));

    $response->assertForbidden();
});

it('lets an admin list gallery photos', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Gallery::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.galleries.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Galleries/Index')
        ->has('galleries.data', 2)
    );
});

it('lets an admin upload a gallery photo', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.galleries.store'), [
        'caption' => 'Nos chatons au jardin',
        'position' => 1,
        'image' => UploadedFile::fake()->image('kittens.jpg'),
    ]);

    $response->assertRedirect(route('admin.galleries.index'));
    $gallery = Gallery::first();
    expect($gallery)->not->toBeNull();
    expect($gallery->getFirstMedia('image'))->not->toBeNull();
});

it('requires an image when creating a gallery entry', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.galleries.store'), [
        'caption' => 'Sans photo',
    ]);

    $response->assertSessionHasErrors('image');
});

it('lets an admin delete a gallery photo', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $gallery = Gallery::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.galleries.destroy', $gallery));

    $response->assertRedirect(route('admin.galleries.index'));
    expect(Gallery::find($gallery->id))->toBeNull();
});
