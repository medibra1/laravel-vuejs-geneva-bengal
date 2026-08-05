<?php

use App\Models\Cat;
use App\Models\ContactRequest;
use App\Models\FaqItem;
use App\Models\Gallery;
use App\Models\Litter;
use App\Models\Owner;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Database\Seeders\ColorSeeder;
use Database\Seeders\DemoDataSeeder;

/**
 * Without this seeder, "a-propos" and "contact" — routes/web.php's own
 * hardcoded literal routes — 404 outright, since there are no Page rows
 * at all otherwise. This is the exact bug report this seeder exists to fix.
 */
it('seeds the two pages routes/web.php depends on by exact slug', function () {
    // Must come before seeding: it rebuilds the whole application
    // container (tearDown + setUp), which would otherwise discard
    // anything seeded first. See tests/Pest.php.
    refreshApplicationWithLocale('fr');
    $this->seed(ColorSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(Page::where('slug', 'a-propos')->exists())->toBeTrue();
    expect(Page::where('slug', 'contact')->exists())->toBeTrue();

    $response = $this->get('/fr/a-propos');
    $response->assertOk();

    $response = $this->get('/fr/contact');
    $response->assertOk();
});

it('seeds CMS pages for both public menu dropdowns', function () {
    $this->seed(ColorSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(Page::where('menu_group', 'race_info')->count())->toBeGreaterThan(0);
    expect(Page::where('menu_group', 'adoption')->count())->toBeGreaterThan(0);
});

it('seeds cats with at least one photo each and a mix of statuses', function () {
    $this->seed(ColorSeeder::class);
    $this->seed(DemoDataSeeder::class);

    $cats = Cat::with(['media', 'statuses'])->get();
    expect($cats)->not->toBeEmpty();
    expect($cats->every(fn (Cat $cat) => $cat->getMedia('photos')->isNotEmpty()))->toBeTrue();
    expect($cats->map(fn (Cat $cat) => $cat->status)->unique()->count())->toBeGreaterThan(1);
});

it('seeds owners covering every adoption-preference state', function () {
    $this->seed(ColorSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(Owner::whereNotNull('desired_cat_id')->exists())->toBeTrue();
    expect(Owner::whereNotNull('desired_color_id')->whereNull('desired_cat_id')->exists())->toBeTrue();
});

it('seeds the rest of the demo content', function () {
    $this->seed(ColorSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(FaqItem::count())->toBeGreaterThan(0);
    expect(Testimonial::count())->toBeGreaterThan(0);
    expect(Litter::count())->toBeGreaterThan(0);
    expect(Gallery::count())->toBeGreaterThan(0);
    expect(ContactRequest::count())->toBeGreaterThan(0);
    expect(SiteSetting::get('deposit_amount'))->not->toBeNull();
});

it('never deletes the shared homepage images it reuses as demo photos', function () {
    $this->seed(ColorSeeder::class);
    $this->seed(DemoDataSeeder::class);

    foreach (['social-1.jpg', 'social-2.jpg', 'social-3.jpg', 'social-4.jpg', 'social-5.jpg', 'social-6.jpg'] as $photo) {
        expect(file_exists(resource_path("images/home/social/{$photo}")))->toBeTrue();
    }
});
