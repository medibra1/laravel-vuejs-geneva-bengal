<?php

use App\Enums\CatType;
use App\Models\Cat;
use App\Models\Page;

it('lists static pages with hreflang alternates for every locale', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

    $xml = $response->getContent();
    expect($xml)->toContain('<loc>'.url('/fr').'</loc>');
    expect($xml)->toContain('<loc>'.url('/en').'</loc>');
    expect($xml)->toContain('hreflang="fr"');
    expect($xml)->toContain('hreflang="en"');
    expect($xml)->toContain('<loc>'.url('/fr/chatons-disponibles').'</loc>');
    expect($xml)->toContain('<loc>'.url('/fr/a-propos').'</loc>');
    expect($xml)->toContain('<loc>'.url('/fr/contact').'</loc>');
    expect($xml)->toContain('<loc>'.url('/fr/galerie').'</loc>');
    expect($xml)->toContain('<loc>'.url('/fr/nos-chats-reproducteurs').'</loc>');
    expect($xml)->toContain('<loc>'.url('/fr/portees-prevues').'</loc>');
});

it('includes every kitten cat, one entry per locale', function () {
    $cat = Cat::factory()->create(['type' => CatType::Kitten, 'name' => 'Simba']);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('<loc>'.url("/fr/chatons-disponibles/{$cat->slug}").'</loc>');
    expect($xml)->toContain('<loc>'.url("/en/chatons-disponibles/{$cat->slug}").'</loc>');
});

it('includes breeding cats alongside kittens, one entry per locale', function () {
    $breeder = Cat::factory()->create(['type' => CatType::Breeder, 'name' => 'Reproducteur']);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('<loc>'.url("/fr/chatons-disponibles/{$breeder->slug}").'</loc>');
    expect($xml)->toContain('<loc>'.url("/en/chatons-disponibles/{$breeder->slug}").'</loc>');
});

it('excludes unpublished pages', function () {
    $unpublished = Page::factory()->create(['slug' => 'brouillon', 'is_published' => false]);
    Page::factory()->create(['slug' => 'race', 'is_published' => true]);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->not->toContain($unpublished->slug);
    expect($xml)->toContain('<loc>'.url('/fr/pages/race').'</loc>');
});

it('caches the generated sitemap instead of rebuilding it on every request', function () {
    Cat::factory()->create(['type' => CatType::Kitten]);
    $first = $this->get('/sitemap.xml')->getContent();

    Cat::factory()->create(['type' => CatType::Kitten, 'name' => 'AddedAfterCache']);
    $second = $this->get('/sitemap.xml')->getContent();

    expect($second)->toBe($first);
    expect($second)->not->toContain('addedaftercache');
});
