<?php

use App\Enums\CatStatus;
use App\Enums\CatType;
use App\Models\Cat;

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
