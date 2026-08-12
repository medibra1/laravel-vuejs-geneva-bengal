<?php

namespace App\Http\Controllers\Public\Concerns;

use App\Enums\GalleryType;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;

/**
 * PageBanner.vue (the secondary-page banner) shares the same hero_slide
 * Gallery rows as HeroSlider.vue (the homepage hero) — an admin managing
 * the slider from the back-office should see the change everywhere it's
 * used, not just on the homepage.
 *
 * Deliberately not shared globally via HandleInertiaRequests::share():
 * a closure there still runs on every request (verified in
 * inertia-laravel's Response::resolveArrayableProperties — "lazy" only
 * means excluded from partial reloads, not evaluated on demand), which
 * would run this query on every admin page too, for nothing. Each public
 * controller whose page actually renders PageBanner calls this instead.
 */
trait SharesHeroSlides
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function heroSlides(): array
    {
        return GalleryResource::collection(
            Gallery::query()->ofType(GalleryType::HeroSlide)->orderBy('position')->with('media')->get(),
        )->resolve();
    }
}
