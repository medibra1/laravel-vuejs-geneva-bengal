<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->post(route('admin.media.upload'), [
        'image' => UploadedFile::fake()->image('figure.jpg'),
    ]);

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->post(route('admin.media.upload'), [
        'image' => UploadedFile::fake()->image('figure.jpg'),
    ]);

    $response->assertForbidden();
});

it('lets an admin upload an image for the rich text editor and returns its URL', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.media.upload'), [
        'image' => UploadedFile::fake()->image('figure.jpg'),
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['url']);
    expect($response->json('url'))->toContain('figure');
});

it('rejects a non-image file', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.media.upload'), [
        'image' => UploadedFile::fake()->create('notes.pdf', 100),
    ]);

    $response->assertSessionHasErrors('image');
});
