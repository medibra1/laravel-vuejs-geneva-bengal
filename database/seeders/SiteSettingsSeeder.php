<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;
use phpDocumentor\Reflection\Types\Null_;

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
        SiteSetting::set('social_youtube', null);
        // No real TikTok URL provided yet — left null until the client
        // gives one, fillable via the admin Settings form in the meantime.
        SiteSetting::set('social_tiktok', null);
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
}
