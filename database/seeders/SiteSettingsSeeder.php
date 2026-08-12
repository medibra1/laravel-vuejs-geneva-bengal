<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Real site settings (social links, address, deposit/price defaults, SEO
 * defaults) — always run (dev/staging/prod), unlike DemoDataSeeder's
 * Faker-generated content. Without this, a fresh production deploy would
 * go live with an empty admin Settings form and a footer/header with no
 * social links or address (SiteSetting::get() falls back to null).
 *
 * Idempotent (SiteSetting::set() is already an updateOrCreate), same
 * convention as ContentPagesSeeder.
 */
class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::set('social_facebook', 'https://facebook.com/people/Geneva-Bengals/100076101586572');
        SiteSetting::set('social_instagram', 'https://instagram.com/geneva_bengals/');
        // No real YouTube URL provided — the placeholder that used to live
        // here (carried over from DemoDataSeeder's original
        // seedSiteSettings()) was never actually the client's channel,
        // left null until a real one is given, same as social_tiktok below.
        SiteSetting::set('social_youtube', null);
        // No real TikTok URL provided yet — left null until the client
        // gives one, fillable via the admin Settings form in the meantime.
        SiteSetting::set('social_tiktok', null);
        SiteSetting::set('address', '1209 Genève, Suisse');
        // No real phone/email provided yet — left null until the client
        // gives them, fillable via the admin Settings form in the
        // meantime. Public\PublicLayout.vue/Contact only render these when
        // set, same convention as social_tiktok/social_youtube above.
        SiteSetting::set('phone', null);
        SiteSetting::set('email', null);
        SiteSetting::set('deposit_amount', 50000);
        SiteSetting::set('price_range_min', 150000);
        SiteSetting::set('price_range_max', 350000);
        SiteSetting::set('default_seo_title', 'Geneva Bengal | Éleveur de chats Bengal à Genève');
        SiteSetting::set(
            'default_seo_description',
            "Élevage de chats Bengal à Genève, Suisse. Chatons en parfaite santé, élevés avec amour, disponibles à l'adoption.",
        );

        $this->seedLogo();
    }

    /**
     * Imports the current hardcoded header logo as the initial value of
     * the dynamic one — firstOrCreate() + wasRecentlyCreated (same pattern
     * as HomeGallerySeeder) so a second run never re-imports it once an
     * admin has replaced it through the Settings screen.
     */
    private function seedLogo(): void
    {
        $logo = SiteSetting::query()->firstOrCreate(['key' => 'logo']);

        if (! $logo->wasRecentlyCreated) {
            return;
        }

        $logo->addMedia(resource_path('images/shared/logo-gb.png'))
            ->preservingOriginal()
            ->toMediaCollection('logo');
    }
}
