<?php

use App\Models\Gallery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('lists gallery photos ordered by position', function () {
    refreshApplicationWithLocale('fr');

    $second = Gallery::factory()->create(['caption' => 'Second', 'position' => 1]);
    $second->addMedia(UploadedFile::fake()->image('second.jpg'))->toMediaCollection('image');

    $first = Gallery::factory()->create(['caption' => 'First', 'position' => 0]);
    $first->addMedia(UploadedFile::fake()->image('first.jpg'))->toMediaCollection('image');

    $response = $this->get('/fr/galerie');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Galerie')
        ->has('galleries', 2)
        ->where('galleries.0.caption', 'First')
        ->where('galleries.1.caption', 'Second')
    );
});

it('shows an empty state with no gallery photos', function () {
    refreshApplicationWithLocale('fr');

    $response = $this->get('/fr/galerie');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Galerie')
        ->has('galleries', 0)
    );
});
