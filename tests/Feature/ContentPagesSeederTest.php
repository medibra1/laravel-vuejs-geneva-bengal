<?php

use App\Models\FaqItem;
use App\Models\Page;
use Database\Seeders\ContentPagesSeeder;

/**
 * Real editorial content, always seeded (dev/staging/prod) — see
 * ContentPagesSeeder and DatabaseSeeder. Without it, a fresh deploy would
 * go live with "a-propos"/"contact" 404ing (routes/web.php's own literal
 * routes) and empty "Informations sur le Bengal"/"Adoption" nav dropdowns.
 */
it('seeds the two pages routes/web.php depends on by exact slug', function () {
    // Must come before seeding: it rebuilds the whole application
    // container (tearDown + setUp), which would otherwise discard
    // anything seeded first. See tests/Pest.php.
    refreshApplicationWithLocale('fr');
    $this->seed(ContentPagesSeeder::class);

    expect(Page::where('slug', 'a-propos')->exists())->toBeTrue();
    expect(Page::where('slug', 'contact')->exists())->toBeTrue();

    $response = $this->get('/fr/a-propos');
    $response->assertOk();

    $response = $this->get('/fr/contact');
    $response->assertOk();
});

it('seeds every CMS page for both public menu dropdowns', function () {
    $this->seed(ContentPagesSeeder::class);

    // 5 race_info pages (characteristics, patterns/colors, personality,
    // diet, health) and 4 adoption-group pages (steps, introducing a
    // kitten, pricing, FAQ) — see ContentPagesSeeder::raceInfoPages()/
    // adoptionPages().
    expect(Page::where('menu_group', 'race_info')->count())->toBe(5);
    expect(Page::where('menu_group', 'adoption')->count())->toBe(4);
    expect(Page::where('slug', 'faq')->where('menu_group', 'adoption')->exists())->toBeTrue();
});

it('seeds real body content for every menu page, not placeholder text', function () {
    $this->seed(ContentPagesSeeder::class);

    $menuPages = Page::whereNotNull('menu_group')->get();

    foreach ($menuPages as $page) {
        expect($page->getTranslation('body', 'fr'))->not->toBeEmpty();
        expect($page->getTranslation('body', 'en'))->not->toBeEmpty();
        expect($page->getTranslation('meta_description', 'fr'))->not->toBeEmpty();
    }

    // A real content bug (e.g. a copy/paste mistake reusing the same
    // paragraph everywhere) would collapse these to fewer distinct values.
    // getTranslation(), not the plain ->body accessor: spatie/laravel-
    // translatable's HasTranslations resolves property access to the
    // *current locale's* string, not the {fr, en} array — see
    // PageController's own comment on this same gotcha.
    $bodies = $menuPages->map(fn (Page $page) => $page->getTranslation('body', 'fr'));
    expect($bodies->unique())->toHaveCount($menuPages->count());
});

it('seeds the FAQ items with real answers', function () {
    $this->seed(ContentPagesSeeder::class);

    expect(FaqItem::count())->toBe(4);
    FaqItem::all()->each(function (FaqItem $item) {
        expect($item->getTranslation('answer', 'fr'))->not->toBeEmpty();
        expect($item->getTranslation('answer', 'en'))->not->toBeEmpty();
    });
});

it('is idempotent — safe to run more than once without duplicating content', function () {
    $this->seed(ContentPagesSeeder::class);
    $this->seed(ContentPagesSeeder::class);

    expect(Page::count())->toBe(11); // a-propos + contact + 5 race_info + 4 adoption
    expect(FaqItem::count())->toBe(4);
});
