<?php

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

it('404s on an unpublished page', function () {
    refreshApplicationWithLocale('fr');

    Page::factory()->create(['slug' => 'a-propos', 'is_published' => false]);

    $response = $this->get('/fr/a-propos');

    $response->assertNotFound();
});
