<?php

use App\Models\Cat;
use App\Models\Color;
use App\Models\Deposit;
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
    $response = $this->get(route('admin.cats.adoption.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.cats.adoption.index'));

    $response->assertForbidden();
});

it('lets an admin list only kitten/cat types, not breeders', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Cat::factory()->count(2)->create(['type' => 'chaton']);
    Cat::factory()->create(['type' => 'chat']);
    Cat::factory()->create(['type' => 'reproducteur']);

    $response = $this->actingAs($admin)->get(route('admin.cats.adoption.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Cats/Adoption/Index')
        ->has('cats.data', 3)
    );
});

it('lets an admin create a kitten, defaulting its status to available', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.cats.adoption.store'), [
        'name' => 'Simba',
        'type' => 'chaton',
        'sex' => 'male',
        'color_id' => $color->id,
        'description' => ['fr' => 'Un chaton joueur', 'en' => 'A playful kitten'],
        'litter_trained' => true,
        'neutered' => false,
    ]);

    $response->assertRedirect(route('admin.cats.adoption.index'));

    $cat = Cat::firstWhere('name', 'Simba');
    expect($cat)->not->toBeNull();
    expect($cat->status)->toBe('disponible');
});

it('refuses to create a cat typed as a breeder through the adoption section', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.cats.adoption.store'), [
        'name' => 'Simba',
        'type' => 'reproducteur',
        'sex' => 'male',
        'color_id' => $color->id,
        'description' => ['fr' => 'x', 'en' => 'y'],
        'litter_trained' => true,
        'neutered' => false,
    ]);

    $response->assertSessionHasErrors('type');
});

it('lets an admin transition a cat status', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'chaton']);
    $cat->setStatus('disponible');

    $response = $this->actingAs($admin)->put(route('admin.cats.adoption.update', $cat), [
        'name' => $cat->name,
        'type' => $cat->type->value,
        'sex' => $cat->sex->value,
        'color_id' => $cat->color_id,
        'description' => ['fr' => 'x', 'en' => 'y'],
        'litter_trained' => true,
        'neutered' => false,
        'status' => 'en_attente',
    ]);

    $response->assertRedirect(route('admin.cats.adoption.index'));
    expect($cat->fresh()->status)->toBe('en_attente');
});

it('lets an admin delete a cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'chaton']);

    $response = $this->actingAs($admin)->delete(route('admin.cats.adoption.destroy', $cat));

    $response->assertRedirect(route('admin.cats.adoption.index'));
    expect(Cat::find($cat->id))->toBeNull();
});

it('lets an admin attach multiple photos to a cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();

    $this->actingAs($admin)->post(route('admin.cats.adoption.store'), [
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
    $cat = Cat::factory()->create(['type' => 'chaton']);
    $cat->addMedia(UploadedFile::fake()->image('one.jpg'))->toMediaCollection('photos');
    $keep = $cat->addMedia(UploadedFile::fake()->image('two.jpg'))->toMediaCollection('photos');

    $photoToDelete = $cat->getMedia('photos')->first(fn ($media) => $media->id !== $keep->id);

    $response = $this->actingAs($admin)->delete(route('admin.cats.adoption.photos.destroy', [$cat, $photoToDelete]));

    $response->assertRedirect();
    expect($cat->fresh()->getMedia('photos'))->toHaveCount(1);
    expect($cat->fresh()->getMedia('photos')->first()->id)->toBe($keep->id);
});

it('exposes a cat\'s own deposits on edit(), not another cat\'s', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'chaton']);
    $otherCat = Cat::factory()->create(['type' => 'chaton']);
    $deposit = Deposit::factory()->paid()->create(['cat_id' => $cat->id]);
    Deposit::factory()->create(['cat_id' => $otherCat->id]);

    $response = $this->actingAs($admin)->get(route('admin.cats.adoption.edit', $cat));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Cats/Adoption/Form')
        ->has('cat.deposits', 1)
        ->where('cat.deposits.0.id', $deposit->id)
    );
});

it('refuses to edit a breeder cat through the adoption section', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $breeder = Cat::factory()->create(['type' => 'reproducteur']);

    $response = $this->actingAs($admin)->get(route('admin.cats.adoption.edit', $breeder));

    $response->assertNotFound();
});

it('refuses to delete a breeder cat through the adoption section', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $breeder = Cat::factory()->create(['type' => 'reproducteur']);

    $response = $this->actingAs($admin)->delete(route('admin.cats.adoption.destroy', $breeder));

    $response->assertNotFound();
    expect(Cat::find($breeder->id))->not->toBeNull();
});
