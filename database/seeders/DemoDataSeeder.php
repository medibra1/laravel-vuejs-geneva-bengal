<?php

namespace Database\Seeders;

use App\Enums\CatStatus;
use App\Enums\CatType;
use App\Models\Cat;
use App\Models\Color;
use App\Models\ContactRequest;
use App\Models\FaqItem;
use App\Models\Gallery;
use App\Models\Litter;
use App\Models\Owner;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Faker-driven demo content so the site actually has something to look at
 * in local dev — without this, every CMS-backed public page 404s (no Page
 * rows at all, including "a-propos"/"contact", which routes/web.php
 * depends on existing) and every admin list is empty. Never run in
 * production — see DatabaseSeeder.
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
        $this->seedPages();
        $this->seedFaqItems();
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

    /**
     * "a-propos" and "contact" are load-bearing — routes/web.php's
     * literal routes resolve them by that exact slug, so without these
     * two rows those two routes 404 outright. The rest populate
     * PublicLayout.vue's two CMS-driven dropdown menus (menu_group
     * race_info / adoption).
     */
    private function seedPages(): void
    {
        Page::create([
            'title' => ['fr' => 'À propos', 'en' => 'About'],
            'body' => [
                'fr' => 'Geneva Bengal est un élevage familial de chats Bengal basé à Genève, en Suisse. Depuis 2020, nous élevons des chatons en parfaite santé, avec une apparence et un comportement à vous faire rêver.',
                'en' => 'Geneva Bengal is a family-run Bengal cattery based in Geneva, Switzerland. Since 2020, we have been breeding kittens in perfect health, with a look and temperament to dream of.',
            ],
            'meta_title' => ['fr' => 'À propos — Geneva Bengal', 'en' => 'About — Geneva Bengal'],
            'meta_description' => [
                'fr' => 'Découvrez notre élevage de chats Bengal à Genève : notre histoire, notre passion, et les témoignages de familles qui nous ont fait confiance.',
                'en' => 'Discover our Bengal cattery in Geneva: our story, our passion, and testimonials from families who trusted us.',
            ],
            'is_published' => true,
        ]);

        Page::create([
            'title' => ['fr' => 'Contact', 'en' => 'Contact'],
            'body' => [
                'fr' => "Une question sur nos chatons disponibles, une envie d'adopter ou de vous inscrire sur notre liste d'attente ? Écrivez-nous, nous vous répondrons rapidement.",
                'en' => 'A question about our available kittens, or would you like to adopt or join our waiting list? Write to us, we will get back to you quickly.',
            ],
            'meta_title' => ['fr' => 'Contact — Geneva Bengal', 'en' => 'Contact — Geneva Bengal'],
            'meta_description' => [
                'fr' => "Contactez notre élevage Geneva Bengal pour toute question sur l'adoption d'un chaton Bengal à Genève.",
                'en' => 'Contact our Geneva Bengal cattery for any question about adopting a Bengal kitten in Geneva.',
            ],
            'is_published' => true,
        ]);

        $raceInfoPages = [
            ['fr' => 'Caractéristiques de la race', 'en' => 'Breed characteristics'],
            ['fr' => 'Motifs et couleurs', 'en' => 'Patterns and colors'],
            ['fr' => 'Personnalité du chat Bengal', 'en' => 'Bengal cat personality'],
            ['fr' => 'Alimentation du chat Bengal', 'en' => 'Bengal cat diet'],
            ['fr' => 'Santé du chat Bengal', 'en' => 'Bengal cat health'],
        ];

        foreach ($raceInfoPages as $order => $title) {
            Page::create([
                'menu_group' => 'race_info',
                'order' => $order,
                'title' => $title,
                'body' => ['fr' => fake()->paragraphs(3, true), 'en' => fake()->paragraphs(3, true)],
                'meta_title' => [$title['fr'].' — Geneva Bengal', $title['en'].' — Geneva Bengal'],
                'meta_description' => ['fr' => fake()->sentence(15), 'en' => fake()->sentence(15)],
                'is_published' => true,
            ]);
        }

        $adoptionPages = [
            ['fr' => 'Étapes pour adopter un chaton Bengal', 'en' => 'Steps to adopt a Bengal kitten'],
            ['fr' => "Introduction d'un nouveau chaton à la maison", 'en' => 'Introducing a new kitten at home'],
            ['fr' => 'Prix de nos chats Bengal', 'en' => 'Prices of our Bengal cats'],
        ];

        foreach ($adoptionPages as $order => $title) {
            Page::create([
                'menu_group' => 'adoption',
                'order' => $order,
                'title' => $title,
                'body' => ['fr' => fake()->paragraphs(3, true), 'en' => fake()->paragraphs(3, true)],
                'meta_title' => [$title['fr'].' — Geneva Bengal', $title['en'].' — Geneva Bengal'],
                'meta_description' => ['fr' => fake()->sentence(15), 'en' => fake()->sentence(15)],
                'is_published' => true,
            ]);
        }
    }

    private function seedFaqItems(): void
    {
        $faqs = [
            ['fr' => 'Quel est le prix d\'un chaton Bengal ?', 'en' => 'What is the price of a Bengal kitten?'],
            ['fr' => 'Livrez-vous à l\'international ?', 'en' => 'Do you ship internationally?'],
            ['fr' => 'Quel est le délai d\'attente pour adopter ?', 'en' => 'What is the waiting time to adopt?'],
            ['fr' => 'Les chatons sont-ils vaccinés et vermifugés ?', 'en' => 'Are the kittens vaccinated and dewormed?'],
        ];

        foreach ($faqs as $order => $question) {
            FaqItem::create([
                'question' => $question,
                'answer' => ['fr' => fake()->paragraph(), 'en' => fake()->paragraph()],
                'order' => $order,
            ]);
        }
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
