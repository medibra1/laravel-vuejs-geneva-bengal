<?php

use App\Models\Page;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.pages.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.pages.index'));

    $response->assertForbidden();
});

it('lets an admin list pages', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Page::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.pages.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Pages/Index')
        ->has('pages.data', 2)
    );
});

it('lets an admin create a page with a slug generated from the French title', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.pages.store'), [
        'menu_group' => 'race_info',
        'order' => 1,
        'title' => ['fr' => 'Caractéristiques de la race', 'en' => 'Breed characteristics'],
        'body' => ['fr' => 'Texte', 'en' => 'Text'],
        'is_published' => true,
    ]);

    $response->assertRedirect(route('admin.pages.index'));
    $page = Page::first();
    expect($page->slug)->toBe('caracteristiques-de-la-race');
    expect($page->getTranslation('title', 'en'))->toBe('Breed characteristics');
});

it('lets an admin update a page', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $page = Page::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.pages.update', $page), [
        'menu_group' => $page->menu_group,
        'order' => $page->order,
        'title' => ['fr' => 'Titre modifié', 'en' => 'Updated title'],
        'body' => ['fr' => 'x', 'en' => 'y'],
        'is_published' => false,
    ]);

    $response->assertRedirect(route('admin.pages.index'));
    expect($page->fresh()->is_published)->toBeFalse();
});

it('lets an admin delete a page', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $page = Page::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.pages.destroy', $page));

    $response->assertRedirect(route('admin.pages.index'));
    expect(Page::find($page->id))->toBeNull();
});
