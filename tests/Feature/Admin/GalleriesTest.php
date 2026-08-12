<?php

use App\Enums\GalleryType;
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

    $response->assertRedirect(route('admin.galleries.index', ['type' => 'gallery']));
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

    $response->assertRedirect(route('admin.galleries.index', ['type' => 'gallery']));
    expect(Gallery::find($gallery->id))->toBeNull();
});

it('filters the index by type, defaulting to gallery', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Gallery::factory()->count(2)->create(['type' => 'gallery']);
    Gallery::factory()->count(1)->create(['type' => 'hero_slide']);

    $default = $this->actingAs($admin)->get(route('admin.galleries.index'));
    $default->assertInertia(fn ($page) => $page->where('type', 'gallery')->has('galleries.data', 2));

    $sliders = $this->actingAs($admin)->get(route('admin.galleries.index', ['type' => 'hero_slide']));
    $sliders->assertInertia(fn ($page) => $page->where('type', 'hero_slide')->has('galleries.data', 1));
});

it('creates a hero_slide entry when type is submitted', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.galleries.store'), [
        'type' => 'hero_slide',
        'image' => UploadedFile::fake()->image('slide.jpg'),
    ]);

    $response->assertRedirect(route('admin.galleries.index', ['type' => 'hero_slide']));
    expect(Gallery::first()->type)->toBe(GalleryType::HeroSlide);
});

it('rejects a new hero_slide once the limit is reached', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Gallery::factory()->count(5)->create(['type' => 'hero_slide']);

    $response = $this->actingAs($admin)->post(route('admin.galleries.store'), [
        'type' => 'hero_slide',
        'image' => UploadedFile::fake()->image('slide.jpg'),
    ]);

    $response->assertSessionHasErrors('type');
    expect(Gallery::query()->ofType(GalleryType::HeroSlide)->count())->toBe(5);
});

it('rejects a new social_tile once the limit is reached', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Gallery::factory()->count(6)->create(['type' => 'social_tile']);

    $response = $this->actingAs($admin)->post(route('admin.galleries.store'), [
        'type' => 'social_tile',
        'image' => UploadedFile::fake()->image('tile.jpg'),
    ]);

    $response->assertSessionHasErrors('type');
    expect(Gallery::query()->ofType(GalleryType::SocialTile)->count())->toBe(6);
});

it('does not cap the plain gallery type', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Gallery::factory()->count(10)->create(['type' => 'gallery']);

    $response = $this->actingAs($admin)->post(route('admin.galleries.store'), [
        'type' => 'gallery',
        'image' => UploadedFile::fake()->image('extra.jpg'),
    ]);

    $response->assertSessionDoesntHaveErrors('type');
    expect(Gallery::query()->ofType(GalleryType::Gallery)->count())->toBe(11);
});
