<?php

use App\Enums\GalleryType;
use App\Models\Gallery;
use Database\Seeders\HomeGallerySeeder;

/**
 * One-off import of the homepage's hardcoded slider/social images into
 * real Gallery rows — never called automatically outside DatabaseSeeder
 * (idempotent there too), run manually per environment. See
 * HomeGallerySeeder.
 */
it('imports the 3 hero slides and 6 social tiles with media', function () {
    $this->seed(HomeGallerySeeder::class);

    expect(Gallery::query()->ofType(GalleryType::HeroSlide)->count())->toBe(3);
    expect(Gallery::query()->ofType(GalleryType::SocialTile)->count())->toBe(6);

    $slides = Gallery::query()->ofType(GalleryType::HeroSlide)->orderBy('position')->get();
    expect($slides->pluck('position')->all())->toBe([1, 2, 3]);

    foreach ($slides as $slide) {
        expect($slide->getFirstMedia('image'))->not->toBeNull();
    }

    $tiles = Gallery::query()->ofType(GalleryType::SocialTile)->orderBy('position')->get();
    expect($tiles->pluck('position')->all())->toBe([1, 2, 3, 4, 5, 6]);
});

it('is idempotent — re-running does not duplicate rows or reprocess media', function () {
    $this->seed(HomeGallerySeeder::class);
    $firstMediaId = Gallery::query()->ofType(GalleryType::HeroSlide)->where('position', 1)->first()->getFirstMedia('image')->id;

    $this->seed(HomeGallerySeeder::class);

    expect(Gallery::query()->ofType(GalleryType::HeroSlide)->count())->toBe(3);
    expect(Gallery::query()->ofType(GalleryType::SocialTile)->count())->toBe(6);
    expect(Gallery::query()->ofType(GalleryType::HeroSlide)->where('position', 1)->first()->getFirstMedia('image')->id)
        ->toBe($firstMediaId);
});

it('does not touch pre-existing gallery rows of type gallery', function () {
    Gallery::factory()->create(['type' => 'gallery', 'position' => 1]);

    $this->seed(HomeGallerySeeder::class);

    expect(Gallery::query()->ofType(GalleryType::Gallery)->count())->toBe(1);
});
