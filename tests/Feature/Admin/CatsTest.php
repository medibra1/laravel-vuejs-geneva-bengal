<?php

use App\Models\Cat;
use App\Models\Color;
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
    $response = $this->get(route('admin.cats.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.cats.index'));

    $response->assertForbidden();
});

it('lets an admin list cats', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Cat::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('admin.cats.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Cats/Index')
        ->has('cats.data', 3)
    );
});

it('lets an admin create a cat, defaulting its status to available', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.cats.store'), [
        'name' => 'Simba',
        'type' => 'chaton',
        'sex' => 'male',
        'color_id' => $color->id,
        'description' => ['fr' => 'Un chaton joueur', 'en' => 'A playful kitten'],
        'litter_trained' => true,
        'neutered' => false,
    ]);

    $response->assertRedirect(route('admin.cats.index'));

    $cat = Cat::firstWhere('name', 'Simba');
    expect($cat)->not->toBeNull();
    expect($cat->status)->toBe('disponible');
});

it('lets an admin transition a cat status', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus('disponible');

    $response = $this->actingAs($admin)->put(route('admin.cats.update', $cat), [
        'name' => $cat->name,
        'type' => $cat->type->value,
        'sex' => $cat->sex->value,
        'color_id' => $cat->color_id,
        'description' => ['fr' => 'x', 'en' => 'y'],
        'litter_trained' => true,
        'neutered' => false,
        'status' => 'en_attente',
    ]);

    $response->assertRedirect(route('admin.cats.index'));
    expect($cat->fresh()->status)->toBe('en_attente');
});

it('lets an admin delete a cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.cats.destroy', $cat));

    $response->assertRedirect(route('admin.cats.index'));
    expect(Cat::find($cat->id))->toBeNull();
});

it('lets an admin attach multiple photos to a cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();

    $this->actingAs($admin)->post(route('admin.cats.store'), [
        'name' => 'Simba',
        'type' => 'chaton',
        'sex' => 'male',
        'color_id' => $color->id,
        'description' => ['fr' => 'x', 'en' => 'y'],
        'litter_trained' => true,
        'neutered' => false,
        'photos' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ],
    ]);

    $cat = Cat::firstWhere('name', 'Simba');
    expect($cat->getMedia('photos'))->toHaveCount(2);
});

it('lets an admin delete a single photo without deleting the cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->addMedia(UploadedFile::fake()->image('one.jpg'))->toMediaCollection('photos');
    $keep = $cat->addMedia(UploadedFile::fake()->image('two.jpg'))->toMediaCollection('photos');

    $photoToDelete = $cat->getMedia('photos')->first(fn ($media) => $media->id !== $keep->id);

    $response = $this->actingAs($admin)->delete(route('admin.cats.photos.destroy', [$cat, $photoToDelete]));

    $response->assertRedirect();
    expect($cat->fresh()->getMedia('photos'))->toHaveCount(1);
    expect($cat->fresh()->getMedia('photos')->first()->id)->toBe($keep->id);
});

it('refuses to delete a photo belonging to a different cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $otherCat = Cat::factory()->create();
    $media = $otherCat->addMedia(UploadedFile::fake()->image('one.jpg'))->toMediaCollection('photos');

    $response = $this->actingAs($admin)->delete(route('admin.cats.photos.destroy', [$cat, $media]));

    $response->assertNotFound();
    expect($otherCat->fresh()->getMedia('photos'))->toHaveCount(1);
});
