<?php

use App\Models\Cat;
use App\Models\Color;
use App\Models\Litter;
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
    $response = $this->get(route('admin.cats.breeders.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.cats.breeders.index'));

    $response->assertForbidden();
});

it('lets an admin list only breeder cats, not kittens/cats', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Cat::factory()->count(2)->create(['type' => 'reproducteur']);
    Cat::factory()->create(['type' => 'chaton']);
    Cat::factory()->create(['type' => 'chat']);

    $response = $this->actingAs($admin)->get(route('admin.cats.breeders.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Cats/Breeders/Index')
        ->has('cats.data', 2)
    );
});

it('shows how many litters each breeder is linked to', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $sire = Cat::factory()->create(['type' => 'reproducteur']);
    Litter::factory()->create(['sire_cat_id' => $sire->id]);
    Litter::factory()->create(['sire_cat_id' => $sire->id]);
    Litter::factory()->create(['dam_cat_id' => $sire->id]);

    $response = $this->actingAs($admin)->get(route('admin.cats.breeders.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('cats.data.0.sire_litters_count', 2)
        ->where('cats.data.0.dam_litters_count', 1)
    );
});

// --- index filters/sort ---

it('filters the breeders list by color', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $silver = Color::factory()->create();
    $brown = Color::factory()->create();
    Cat::factory()->create(['type' => 'reproducteur', 'color_id' => $silver->id]);
    Cat::factory()->create(['type' => 'reproducteur', 'color_id' => $brown->id]);

    $response = $this->actingAs($admin)->get(route('admin.cats.breeders.index', ['filter' => ['color_id' => $silver->id]]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('cats.data', 1));
});

it('searches the breeders list across both name and eye color', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Cat::factory()->create(['type' => 'reproducteur', 'name' => 'Simba']);
    Cat::factory()->create(['type' => 'reproducteur', 'name' => 'Nala', 'eye_color' => 'Vert Simba']);
    Cat::factory()->create(['type' => 'reproducteur', 'name' => 'Rocky', 'eye_color' => 'Or']);

    $response = $this->actingAs($admin)->get(route('admin.cats.breeders.index', ['filter' => ['search' => 'Simba']]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('cats.data', 2));
});

it('sorts the breeders list by name', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Cat::factory()->create(['type' => 'reproducteur', 'name' => 'Zorro']);
    Cat::factory()->create(['type' => 'reproducteur', 'name' => 'Alfa']);

    $response = $this->actingAs($admin)->get(route('admin.cats.breeders.index', ['sort' => 'name']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('cats.data.0.name', 'Alfa')
        ->where('cats.data.1.name', 'Zorro')
    );
});

it('lets an admin create a breeder cat, always typed as reproducteur', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.cats.breeders.store'), [
        'name' => 'Rocky',
        'sex' => 'male',
        'color_id' => $color->id,
        'description' => ['fr' => 'Un reproducteur calme', 'en' => 'A calm sire'],
        'litter_trained' => true,
        'neutered' => false,
    ]);

    $response->assertRedirect(route('admin.cats.breeders.index'));

    $cat = Cat::firstWhere('name', 'Rocky');
    expect($cat)->not->toBeNull();
    expect($cat->type->value)->toBe('reproducteur');
    expect($cat->price)->toBeNull();
});

it('lets an admin update a breeder cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'reproducteur']);

    $response = $this->actingAs($admin)->put(route('admin.cats.breeders.update', $cat), [
        'name' => 'Renamed',
        'sex' => $cat->sex->value,
        'color_id' => $cat->color_id,
        'description' => ['fr' => 'x', 'en' => 'y'],
        'litter_trained' => true,
        'neutered' => false,
    ]);

    $response->assertRedirect(route('admin.cats.breeders.index'));
    expect($cat->fresh()->name)->toBe('Renamed');
    expect($cat->fresh()->type->value)->toBe('reproducteur');
});

it('lets an admin delete a breeder cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'reproducteur']);

    $response = $this->actingAs($admin)->delete(route('admin.cats.breeders.destroy', $cat));

    $response->assertRedirect(route('admin.cats.breeders.index'));
    expect(Cat::find($cat->id))->toBeNull();
});

it('lets an admin delete a single photo without deleting the breeder cat', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create(['type' => 'reproducteur']);
    $cat->addMedia(UploadedFile::fake()->image('one.jpg'))->toMediaCollection('photos');
    $keep = $cat->addMedia(UploadedFile::fake()->image('two.jpg'))->toMediaCollection('photos');

    $photoToDelete = $cat->getMedia('photos')->first(fn ($media) => $media->id !== $keep->id);

    $response = $this->actingAs($admin)->delete(route('admin.cats.breeders.photos.destroy', [$cat, $photoToDelete]));

    $response->assertRedirect();
    expect($cat->fresh()->getMedia('photos'))->toHaveCount(1);
});

it('refuses to edit an adoption cat through the breeders section', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $kitten = Cat::factory()->create(['type' => 'chaton']);

    $response = $this->actingAs($admin)->get(route('admin.cats.breeders.edit', $kitten));

    $response->assertNotFound();
});

it('refuses to delete an adoption cat through the breeders section', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $kitten = Cat::factory()->create(['type' => 'chaton']);

    $response = $this->actingAs($admin)->delete(route('admin.cats.breeders.destroy', $kitten));

    $response->assertNotFound();
    expect(Cat::find($kitten->id))->not->toBeNull();
});
