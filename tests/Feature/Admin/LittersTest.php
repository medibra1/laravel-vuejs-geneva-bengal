<?php

use App\Models\Cat;
use App\Models\Color;
use App\Models\Litter;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.litters.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.litters.index'));

    $response->assertForbidden();
});

it('lets an admin list litters', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Litter::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.litters.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Litters/Index')
        ->has('litters.data', 2)
    );
});

it('lets an admin create a litter with a sire and a dam', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();
    $sire = Cat::factory()->create(['color_id' => $color->id, 'sex' => 'male']);
    $dam = Cat::factory()->create(['color_id' => $color->id, 'sex' => 'femelle']);

    $response = $this->actingAs($admin)->post(route('admin.litters.store'), [
        'sire_cat_id' => $sire->id,
        'dam_cat_id' => $dam->id,
        'expected_date' => '2026-12-01',
        'notes' => 'Première portée prévue',
    ]);

    $response->assertRedirect(route('admin.litters.index'));
    $litter = Litter::first();
    expect($litter->sire_cat_id)->toBe($sire->id);
    expect($litter->dam_cat_id)->toBe($dam->id);
});

it('rejects the same cat as both sire and dam', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();
    $cat = Cat::factory()->create(['color_id' => $color->id]);

    $response = $this->actingAs($admin)->post(route('admin.litters.store'), [
        'sire_cat_id' => $cat->id,
        'dam_cat_id' => $cat->id,
    ]);

    $response->assertSessionHasErrors('sire_cat_id');
});

it('lets an admin delete a litter', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $litter = Litter::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.litters.destroy', $litter));

    $response->assertRedirect(route('admin.litters.index'));
    expect(Litter::find($litter->id))->toBeNull();
});
