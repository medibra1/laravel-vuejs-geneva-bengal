<?php

namespace Database\Seeders;

use App\Enums\GalleryType;
use App\Models\Gallery;
use Illuminate\Database\Seeder;

class HomeGallerySeeder extends Seeder
{
    /**
     * One-off import of the homepage's hardcoded hero slider images and
     * social tiles into real Gallery rows, so the admin can manage them
     * through the Gallery screen (see GalleryController) instead of a code
     * change. Not called from DatabaseSeeder — run manually once per
     * environment: `php artisan db:seed --class=HomeGallerySeeder`.
     *
     * Idempotent: safe to re-run in any environment — Gallery::firstOrCreate()
     * on the (type, position) unique index (see the galleries migration)
     * means a second run never duplicates rows, and the wasRecentlyCreated
     * guard in importType() means it never re-runs the sm/md/lg conversions
     * for images already imported.
     */
    public function run(): void
    {
        $this->importType(
            GalleryType::HeroSlide,
            [
                1 => resource_path('images/home/slider-1.jpg'),
                2 => resource_path('images/home/slider-2.jpg'),
                3 => resource_path('images/home/slider-3.jpg'),
            ],
        );

        $this->importType(
            GalleryType::SocialTile,
            [
                1 => resource_path('images/home/social/social-1.jpg'),
                2 => resource_path('images/home/social/social-2.jpg'),
                3 => resource_path('images/home/social/social-3.jpg'),
                4 => resource_path('images/home/social/social-4.jpg'),
                5 => resource_path('images/home/social/social-5.jpg'),
                6 => resource_path('images/home/social/social-6.jpg'),
            ],
        );
    }

    /**
     * @param  array<int, string>  $pathsByPosition
     */
    private function importType(GalleryType $type, array $pathsByPosition): void
    {
        foreach ($pathsByPosition as $position => $path) {
            $gallery = Gallery::firstOrCreate(['type' => $type, 'position' => $position]);

            // firstOrCreate() alone would still avoid duplicating the row on
            // a second run, but without this guard it would re-run addMedia()
            // (and the sm/md/lg conversions) against an already-imported row
            // every time — wasRecentlyCreated is what actually makes this
            // idempotent on the media side, not just the row itself.
            if (! $gallery->wasRecentlyCreated) {
                continue;
            }

            $gallery->addMedia($path)->preservingOriginal()->toMediaCollection('image');
        }
    }
}
