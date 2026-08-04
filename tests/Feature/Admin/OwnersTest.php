<?php

use App\Models\Cat;
use App\Models\Color;
use App\Models\Owner;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.owners.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.owners.index'));

    $response->assertForbidden();
});

it('lets an admin list owners', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Owner::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('admin.owners.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Owners/Index')
        ->has('owners.data', 3)
    );
});

it('lets an admin create an owner', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.owners.store'), [
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean.dupont@example.com',
        'phone' => '+41 22 000 00 00',
        'city' => 'Genève',
    ]);

    $response->assertRedirect(route('admin.owners.index'));
    expect(Owner::firstWhere('email', 'jean.dupont@example.com'))->not->toBeNull();
});

it('rejects a duplicate owner email', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Owner::factory()->create(['email' => 'taken@example.com']);

    $response = $this->actingAs($admin)->post(route('admin.owners.store'), [
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

it('lets an admin update an owner', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.owners.update', $owner), [
        'first_name' => 'Updated',
        'last_name' => $owner->last_name,
        'email' => $owner->email,
    ]);

    $response->assertRedirect(route('admin.owners.index'));
    expect($owner->fresh()->first_name)->toBe('Updated');
});

it('lets an admin record which cat an owner wants to adopt', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.owners.store'), [
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean.dupont@example.com',
        'desired_cat_id' => $cat->id,
    ]);

    $response->assertRedirect(route('admin.owners.index'));
    expect(Owner::firstWhere('email', 'jean.dupont@example.com')->desired_cat_id)->toBe($cat->id);
});

it('lets an admin record a color preference for a waiting-list owner with no specific cat yet', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.owners.store'), [
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean.dupont@example.com',
        'desired_color_id' => $color->id,
    ]);

    $response->assertRedirect(route('admin.owners.index'));
    $owner = Owner::firstWhere('email', 'jean.dupont@example.com');
    expect($owner->desired_color_id)->toBe($color->id);
    expect($owner->desired_cat_id)->toBeNull();
});

it('exposes adoptable cats and colors for the owner form', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $color = Color::factory()->create();
    $available = Cat::factory()->create(['color_id' => $color->id]);
    $available->setStatus('disponible');
    $adopted = Cat::factory()->create(['color_id' => $color->id]);
    $adopted->setStatus('adopte');

    $response = $this->actingAs($admin)->get(route('admin.owners.create'));

    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Owners/Form')
        ->has('cats', 1)
        ->has('colors', 1)
    );
});

it('lets an admin delete an owner', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $owner = Owner::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.owners.destroy', $owner));

    $response->assertRedirect(route('admin.owners.index'));
    expect(Owner::find($owner->id))->toBeNull();
});
