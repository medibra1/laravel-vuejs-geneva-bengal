<?php

use App\Models\Cat;
use App\Models\Color;

it('permanently redirects the old static URLs to their /fr equivalents', function (string $old, string $new) {
    $this->get($old)->assertStatus(301)->assertRedirect(url($new));
})->with([
    'kittens list' => ['/chaton-bengal-a-vendre', '/fr/chatons-disponibles'],
    'gallery' => ['/galleries', '/fr/galerie'],
    'payment' => ['/paiement', '/fr/chatons-disponibles'],
    'newsletter subscribe' => ['/newsletter/abonnement-newsletter', '/fr'],
    'newsletter unsubscribe' => ['/newsletter/desabonnement-newsletter', '/fr'],
]);

it('maps an old "chaton-bengal-<color>" slug to the new color filter', function () {
    Color::factory()->create(['name' => 'Noir / Mélanistique']);

    $this->get('/chaton-bengal-a-vendre/chaton-bengal-noir-melanistique')
        ->assertStatus(301)
        ->assertRedirect(url('/fr/chatons-disponibles/couleur/noir-melanistique'));
});

it('falls back to the kittens list for an unknown color slug', function () {
    $this->get('/chaton-bengal-a-vendre/chaton-bengal-inconnu')
        ->assertStatus(301)
        ->assertRedirect(url('/fr/chatons-disponibles'));
});

it('redirects an old cat URL by its old slug when it still exists', function () {
    $cat = Cat::factory()->create(['slug' => 'bengal-snow-male-2']);

    $this->get("/chaton/{$cat->slug}/Libby")
        ->assertStatus(301)
        ->assertRedirect(url('/fr/chatons-disponibles/bengal-snow-male-2'));
});

it('redirects an old cat URL by its name when the old slug is gone', function () {
    $cat = Cat::factory()->create(['name' => 'Margaret Pruitt']);

    $this->get('/chaton/bengal-bleu-femelle/Margaret%20Pruitt')
        ->assertStatus(301)
        ->assertRedirect(url("/fr/chatons-disponibles/{$cat->slug}"));
});

it('falls back to the kittens list for a cat that no longer exists', function () {
    $this->get('/chaton/bengal-snow-male-9/Fantome')
        ->assertStatus(301)
        ->assertRedirect(url('/fr/chatons-disponibles'));
});
