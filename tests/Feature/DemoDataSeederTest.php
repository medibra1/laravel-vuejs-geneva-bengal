<?php

use App\Models\Cat;
use App\Models\ContactRequest;
use App\Models\Gallery;
use App\Models\Litter;
use App\Models\Owner;
use App\Models\Testimonial;
use Database\Seeders\ColorSeeder;
use Database\Seeders\DemoDataSeeder;

// Pages/FAQ and site settings are real content, always seeded — see
// ContentPagesSeederTest.php/SiteSettingsSeederTest.php, not this file.
// DemoDataSeeder is Faker-generated data only.

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

    expect(Testimonial::count())->toBeGreaterThan(0);
    expect(Litter::count())->toBeGreaterThan(0);
    expect(Gallery::count())->toBeGreaterThan(0);
    expect(ContactRequest::count())->toBeGreaterThan(0);
});

it('never deletes the shared homepage images it reuses as demo photos', function () {
    $this->seed(ColorSeeder::class);
    $this->seed(DemoDataSeeder::class);

    foreach (['social-1.jpg', 'social-2.jpg', 'social-3.jpg', 'social-4.jpg', 'social-5.jpg', 'social-6.jpg'] as $photo) {
        expect(file_exists(resource_path("images/home/social/{$photo}")))->toBeTrue();
    }
});
