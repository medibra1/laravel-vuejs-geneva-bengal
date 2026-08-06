<?php

use App\Models\FaqItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Testimonial;

it('shows the published a-propos page with testimonials', function () {
    refreshApplicationWithLocale('fr');

    Page::factory()->create(['slug' => 'a-propos', 'is_published' => true]);
    Testimonial::factory()->create(['is_published' => true]);

    $response = $this->get('/fr/a-propos');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Page')
        ->where('page.slug', 'a-propos')
        ->has('testimonials', 1)
    );
});

it('shows the published contact page with site settings', function () {
    refreshApplicationWithLocale('fr');

    Page::factory()->create(['slug' => 'contact', 'is_published' => true]);
    SiteSetting::set('address', '1209 Genève, Suisse');

    $response = $this->get('/fr/contact');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Page')
        ->where('page.slug', 'contact')
        ->where('settings.address', '1209 Genève, Suisse')
    );
});

it('shows the published faq page with faq items', function () {
    refreshApplicationWithLocale('fr');

    Page::factory()->create(['slug' => 'faq', 'menu_group' => 'adoption', 'is_published' => true]);
    FaqItem::factory()->create([
        'question' => ['fr' => 'Livrez-vous à l\'international ?', 'en' => 'Do you ship internationally?'],
        'answer' => ['fr' => 'Oui, sous conditions.', 'en' => 'Yes, under conditions.'],
    ]);

    $response = $this->get('/fr/pages/faq');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Page')
        ->has('faqItems', 1)
        ->where('faqItems.0.question', 'Livrez-vous à l\'international ?')
    );
});

it('404s on an unpublished page', function () {
    refreshApplicationWithLocale('fr');

    Page::factory()->create(['slug' => 'a-propos', 'is_published' => false]);

    $response = $this->get('/fr/a-propos');

    $response->assertNotFound();
});
