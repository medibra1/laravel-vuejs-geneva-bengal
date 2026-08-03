<?php

use App\Enums\CatStatus;
use App\Models\Cat;

it('generates a unique slug from the name', function () {
    Cat::factory()->create(['name' => 'Simba']);
    $second = Cat::factory()->create(['name' => 'Simba']);

    expect($second->slug)->toBe('simba-1');
});

it('stores translatable descriptions per locale', function () {
    $cat = Cat::factory()->create([
        'description' => ['fr' => 'Bonjour', 'en' => 'Hello'],
    ]);

    expect($cat->getTranslation('description', 'fr'))->toBe('Bonjour');
    expect($cat->getTranslation('description', 'en'))->toBe('Hello');
});

it('tracks status history instead of overwriting it', function () {
    $cat = Cat::factory()->create();

    $cat->setStatus(CatStatus::Available->value);
    $cat->setStatus(CatStatus::Adopted->value);

    expect($cat->status)->toBe(CatStatus::Adopted->value);
    expect($cat->statuses)->toHaveCount(2);
});
