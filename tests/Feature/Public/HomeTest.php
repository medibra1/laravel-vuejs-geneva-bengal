<?php

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
