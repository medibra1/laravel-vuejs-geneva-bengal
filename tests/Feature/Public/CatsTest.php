<?php

use App\Enums\CatStatus;
use App\Enums\CatType;
use App\Models\Cat;
use App\Models\Color;

it('lists available kittens but excludes adopted ones', function () {
    refreshApplicationWithLocale('fr');

    $available = Cat::factory()->create(['type' => CatType::Kitten]);
    $available->setStatus(CatStatus::Available->value);

    $adopted = Cat::factory()->create(['type' => CatType::Kitten]);
    $adopted->setStatus(CatStatus::Adopted->value);

    $response = $this->get('/fr/chatons-disponibles');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/ChatonsDisponibles')
        ->has('cats', 1)
        ->where('cats.0.id', $available->id)
    );
});

it('excludes a kitten that is en_attente (an active deposit already holds it)', function () {
    refreshApplicationWithLocale('fr');

    $available = Cat::factory()->create(['type' => CatType::Kitten]);
    $available->setStatus(CatStatus::Available->value);

    $pending = Cat::factory()->create(['type' => CatType::Kitten]);
    $pending->setStatus(CatStatus::Pending->value);

    $response = $this->get('/fr/chatons-disponibles');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/ChatonsDisponibles')
        ->has('cats', 1)
        ->where('cats.0.id', $available->id)
    );
});

it('filters kittens by color slug, matching either the primary or secondary color', function () {
    refreshApplicationWithLocale('fr');

    $silver = Color::factory()->create(['name' => 'Silver']);
    $brown = Color::factory()->create(['name' => 'Brown']);

    $primaryMatch = Cat::factory()->create(['type' => CatType::Kitten, 'color_id' => $silver->id]);
    $primaryMatch->setStatus(CatStatus::Available->value);

    $secondaryMatch = Cat::factory()->create(['type' => CatType::Kitten, 'color_id' => $brown->id, 'second_color_id' => $silver->id]);
    $secondaryMatch->setStatus(CatStatus::Available->value);

    $noMatch = Cat::factory()->create(['type' => CatType::Kitten, 'color_id' => $brown->id]);
    $noMatch->setStatus(CatStatus::Available->value);

    $response = $this->get('/fr/chatons-disponibles/couleur/'.$silver->slug);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/ChatonsDisponibles')
        ->has('cats', 2)
        ->where('activeColorSlug', $silver->slug)
    );
});

it('returns a 404 for an unknown color slug', function () {
    refreshApplicationWithLocale('fr');

    $response = $this->get('/fr/chatons-disponibles/couleur/does-not-exist');

    $response->assertNotFound();
});

it('shows a single cat by slug', function () {
    refreshApplicationWithLocale('fr');

    $cat = Cat::factory()->create(['name' => 'Simba', 'type' => CatType::Kitten]);

    $response = $this->get('/fr/chatons-disponibles/'.$cat->slug);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/ChatonDetail')
        ->where('cat.id', $cat->id)
    );
});

it('lists breeding cats, excluding kittens', function () {
    refreshApplicationWithLocale('fr');

    $breeder = Cat::factory()->create(['type' => CatType::Breeder]);
    Cat::factory()->create(['type' => CatType::Kitten]);

    $response = $this->get('/fr/nos-chats-reproducteurs');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Reproducteurs')
        ->has('cats', 1)
        ->where('cats.0.id', $breeder->id)
    );
});

it('shows a breeding cat detail page by the same route as kittens', function () {
    refreshApplicationWithLocale('fr');

    $breeder = Cat::factory()->create(['name' => 'Simba', 'type' => CatType::Breeder]);

    $response = $this->get('/fr/chatons-disponibles/'.$breeder->slug);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/ChatonDetail')
        ->where('cat.type', 'reproducteur')
    );
});
