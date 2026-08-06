<?php

use App\Models\Cat;
use App\Models\Litter;

it('lists upcoming litters ordered by expected date, excluding past ones', function () {
    refreshApplicationWithLocale('fr');

    $sire = Cat::factory()->create(['name' => 'Rocky']);
    $dam = Cat::factory()->create(['name' => 'Bella']);

    $later = Litter::factory()->create([
        'sire_cat_id' => $sire->id,
        'dam_cat_id' => $dam->id,
        'expected_date' => now()->addMonths(2),
    ]);
    $sooner = Litter::factory()->create([
        'sire_cat_id' => $sire->id,
        'dam_cat_id' => $dam->id,
        'expected_date' => now()->addWeeks(2),
    ]);
    Litter::factory()->create(['expected_date' => now()->subMonth()]);

    $response = $this->get('/fr/portees-prevues');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/PorteesPrevues')
        ->has('litters', 2)
        ->where('litters.0.id', $sooner->id)
        ->where('litters.1.id', $later->id)
        ->where('litters.0.sire.name', 'Rocky')
        ->where('litters.0.dam.name', 'Bella')
    );
});

it('shows an empty state with no upcoming litters', function () {
    refreshApplicationWithLocale('fr');

    $response = $this->get('/fr/portees-prevues');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/PorteesPrevues')
        ->has('litters', 0)
    );
});
