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
     * Idempotent on (type, position): re-running skips any pair that
     * already has a row, so it never duplicates entries or re-runs the
     * sm/md/lg conversions for images already imported.
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
            if (Gallery::query()->ofType($type)->where('position', $position)->exists()) {
                continue;
            }

            $gallery = Gallery::create([
                'type' => $type,
                'position' => $position,
            ]);

            $gallery->addMedia($path)->preservingOriginal()->toMediaCollection('image');
        }
    }
}
