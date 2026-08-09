<?php

use App\Models\SiteSetting;
use Database\Seeders\SiteSettingsSeeder;

/**
 * Real site settings, always seeded (dev/staging/prod) — see
 * SiteSettingsSeeder and DatabaseSeeder. Without it, a fresh deploy would
 * go live with no social links/address in the footer/header and an empty
 * admin Settings form.
 */
it('seeds the real social links, address and pricing defaults', function () {
    $this->seed(SiteSettingsSeeder::class);

    expect(SiteSetting::get('social_facebook'))
        ->toBe('https://facebook.com/people/Geneva-Bengals/100076101586572');
    expect(SiteSetting::get('social_instagram'))->toBe('https://instagram.com/geneva_bengals/');
    expect(SiteSetting::get('social_youtube'))->not->toBeNull();
    expect(SiteSetting::get('address'))->toBe('1209 Genève, Suisse');
    expect(SiteSetting::get('deposit_amount'))->not->toBeNull();
    expect(SiteSetting::get('price_range_min'))->not->toBeNull();
    expect(SiteSetting::get('price_range_max'))->not->toBeNull();
    expect(SiteSetting::get('default_seo_title'))->not->toBeNull();
    expect(SiteSetting::get('default_seo_description'))->not->toBeNull();
});

it('is idempotent — re-running does not duplicate rows', function () {
    $this->seed(SiteSettingsSeeder::class);
    $this->seed(SiteSettingsSeeder::class);

    expect(SiteSetting::where('key', 'social_facebook')->count())->toBe(1);
});
