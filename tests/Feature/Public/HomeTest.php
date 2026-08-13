<?php

use App\Models\Gallery;
use App\Models\SiteSetting;

it('falls back to a default title/description when site_settings has none', function () {
    refreshApplicationWithLocale('fr');

    $response = $this->get('/fr');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Home')
        ->where('seo.title', 'Geneva Bengal | Éleveur de chats Bengal à Genève')
        ->has('seo.description')
    );
});

it('uses the site_settings SEO texts when configured', function () {
    refreshApplicationWithLocale('fr');
    SiteSetting::set('default_seo_title', 'Titre personnalisé');
    SiteSetting::set('default_seo_description', 'Description personnalisée');

    $response = $this->get('/fr');

    $response->assertInertia(fn ($page) => $page
        ->where('seo.title', 'Titre personnalisé')
        ->where('seo.description', 'Description personnalisée')
    );
});

it('exposes hero slides and social tiles ordered by position', function () {
    refreshApplicationWithLocale('fr');
    Gallery::factory()->create(['type' => 'hero_slide', 'position' => 2]);
    Gallery::factory()->create(['type' => 'hero_slide', 'position' => 1]);
    Gallery::factory()->create(['type' => 'social_tile', 'position' => 1]);
    Gallery::factory()->create(['type' => 'gallery', 'position' => 1]);

    $response = $this->get('/fr');

    $response->assertInertia(fn ($page) => $page
        ->component('Public/Home')
        ->has('heroSlides', 2)
        ->where('heroSlides.0.position', 1)
        ->where('heroSlides.1.position', 2)
        ->has('socialTiles', 1)
    );
});

it('exposes empty hero slides and social tiles when none exist yet', function () {
    refreshApplicationWithLocale('fr');

    $response = $this->get('/fr');

    $response->assertInertia(fn ($page) => $page
        ->has('heroSlides', 0)
        ->has('socialTiles', 0)
    );
});
