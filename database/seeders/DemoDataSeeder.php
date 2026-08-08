<?php

namespace Database\Seeders;

use App\Enums\CatStatus;
use App\Enums\CatType;
use App\Models\Cat;
use App\Models\Color;
use App\Models\ContactRequest;
use App\Models\Gallery;
use App\Models\Litter;
use App\Models\Owner;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Faker-driven demo content so admin lists (cats, litters, owners,
 * galleries, contact requests, testimonials) actually have something to
 * look at in local dev. Never run in production — see DatabaseSeeder.
 *
 * Real CMS content (pages, FAQ) moved to ContentPagesSeeder — that one
 * always runs, this one is demo/test data only.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Real Bengal photos already in the repo (used on the homepage) —
     * reused here instead of generating/fetching placeholder images.
     * preservingOriginal() is essential: medialibrary's addMedia() moves
     * (deletes) the source file by default, which would break the
     * homepage that also references these same files.
     *
     * @var list<string>
     */
    private const PHOTO_POOL = [
        'social/social-1.jpg', 'social/social-2.jpg', 'social/social-3.jpg',
        'social/social-4.jpg', 'social/social-5.jpg', 'social/social-6.jpg',
    ];

    public function run(): void
    {
        $this->seedSiteSettings();
        $this->seedTestimonials();

        $cats = $this->seedCats();
        $this->seedLitters($cats);
        $this->seedOwners($cats);
        $this->seedGalleries();
        $this->seedContactRequests($cats);
    }

    private function seedSiteSettings(): void
    {
        SiteSetting::set('social_facebook', 'https://facebook.com/genevabengal');
        SiteSetting::set('social_instagram', 'https://instagram.com/genevabengal');
        SiteSetting::set('social_youtube', 'https://youtube.com/@genevabengal');
        SiteSetting::set('social_pinterest', 'https://pinterest.com/genevabengal');
        SiteSetting::set('address', '1209 Genève, Suisse');
        SiteSetting::set('deposit_amount', 50000);
        SiteSetting::set('price_range_min', 150000);
        SiteSetting::set('price_range_max', 350000);
        SiteSetting::set('default_seo_title', 'Geneva Bengal | Éleveur de chats Bengal à Genève');
        SiteSetting::set(
            'default_seo_description',
            "Élevage de chats Bengal à Genève, Suisse. Chatons en parfaite santé, élevés avec amour, disponibles à l'adoption.",
        );
    }

    private function seedTestimonials(): void
    {
        Testimonial::factory()->count(5)->create();
    }

    /**
     * @return Collection<int, Cat>
     */
    private function seedCats()
    {
        $colors = Color::all();

        if ($colors->isEmpty()) {
            $colors = Color::factory()->count(4)->create();
        }

        $cats = Cat::factory()
            ->count(9)
            ->sequence(
                ['type' => CatType::Kitten],
                ['type' => CatType::Kitten],
                ['type' => CatType::Kitten],
                ['type' => CatType::Kitten],
                ['type' => CatType::Kitten],
                ['type' => CatType::Kitten],
                ['type' => CatType::Breeder, 'sex' => 'male'],
                ['type' => CatType::Breeder, 'sex' => 'femelle'],
                ['type' => CatType::Cat],
            )
            ->make()
            ->map(function (Cat $cat) use ($colors) {
                $cat->color_id = $colors->random()->id;
                $cat->save();

                return $cat;
            });

        $statuses = [
            CatStatus::Available, CatStatus::Available, CatStatus::Available,
            CatStatus::Available, CatStatus::Pending, CatStatus::Adopted,
            CatStatus::Available, CatStatus::Available, CatStatus::Available,
        ];

        $cats->each(function (Cat $cat, int $index) use ($statuses) {
            $cat->setStatus($statuses[$index]->value);

            $photo = self::PHOTO_POOL[$index % count(self::PHOTO_POOL)];
            $cat->addMedia(resource_path("images/home/{$photo}"))
                ->preservingOriginal()
                ->toMediaCollection('photos');
        });

        return $cats;
    }

    /**
     * @param  Collection<int, Cat>  $cats
     */
    private function seedLitters($cats): void
    {
        $sire = $cats->first(fn (Cat $cat) => $cat->type === CatType::Breeder && $cat->sex->value === 'male');
        $dam = $cats->first(fn (Cat $cat) => $cat->type === CatType::Breeder && $cat->sex->value === 'femelle');

        Litter::factory()->create([
            'sire_cat_id' => $sire?->id,
            'dam_cat_id' => $dam?->id,
        ]);
    }

    /**
     * @param  Collection<int, Cat>  $cats
     */
    private function seedOwners($cats): void
    {
        $available = $cats->filter(fn (Cat $cat) => $cat->status === CatStatus::Available->value)->values();
        $colors = Color::all();

        // A mix of the three real states this feature exists for: a
        // specific cat already picked out, a waiting-list color
        // preference with no cat yet, and neither (just a contact record).
        Owner::factory()->create(['desired_cat_id' => $available->get(0)?->id]);
        Owner::factory()->create(['desired_cat_id' => $available->get(1)?->id]);
        Owner::factory()->create(['desired_color_id' => $colors->random()->id]);
        Owner::factory()->create(['desired_color_id' => $colors->random()->id]);
        Owner::factory()->count(2)->create();
    }

    private function seedGalleries(): void
    {
        foreach (self::PHOTO_POOL as $position => $photo) {
            Gallery::create(['caption' => fake()->optional()->sentence(), 'position' => $position])
                ->addMedia(resource_path("images/home/{$photo}"))
                ->preservingOriginal()
                ->toMediaCollection('image');
        }
    }

    /**
     * @param  Collection<int, Cat>  $cats
     */
    private function seedContactRequests($cats): void
    {
        ContactRequest::factory()->create(['cat_id' => $cats->first()?->id, 'reason' => 'adopt']);
        ContactRequest::factory()->create(['reason' => 'waiting_list']);
        ContactRequest::factory()->create(['reason' => 'question']);
    }
}
