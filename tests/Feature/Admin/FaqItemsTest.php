<?php

use App\Models\FaqItem;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('redirects guests to login', function () {
    $response = $this->get(route('admin.faq-items.index'));

    $response->assertRedirect(route('login'));
});

it('denies users without the admin or super_admin role', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('admin.faq-items.index'));

    $response->assertForbidden();
});

it('lets an admin list FAQ items', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    FaqItem::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.faq-items.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/FaqItems/Index')
        ->has('faqItems.data', 2)
    );
});

it('lets an admin create a FAQ item', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.faq-items.store'), [
        'question' => ['fr' => 'Combien coûte un chaton ?', 'en' => 'How much does a kitten cost?'],
        'answer' => ['fr' => 'Cela dépend.', 'en' => 'It depends.'],
        'order' => 1,
    ]);

    $response->assertRedirect(route('admin.faq-items.index'));
    expect(FaqItem::count())->toBe(1);
});

it('lets an admin delete a FAQ item', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $faqItem = FaqItem::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.faq-items.destroy', $faqItem));

    $response->assertRedirect(route('admin.faq-items.index'));
    expect(FaqItem::find($faqItem->id))->toBeNull();
});
