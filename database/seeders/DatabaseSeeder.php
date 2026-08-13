<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The first six run in every environment (dev/staging/prod): roles
     * bootstrap, the first super_admin account, the fixed Bengal color
     * reference data, the real CMS pages/FAQ content (see
     * ContentPagesSeeder — without it, a fresh prod deploy would go live
     * with empty "Informations sur le Bengal"/"Adoption" nav dropdowns and
     * "a-propos"/"contact" 404ing outright), the real site settings (see
     * SiteSettingsSeeder — without it, a fresh prod deploy would go live
     * with no social links/address and an empty admin Settings form), and
     * the homepage hero slider/social tile images (see HomeGallerySeeder —
     * idempotent on (type, position), so re-running it here on every
     * `--seed` is a cheap no-op once the rows exist rather than something
     * that needs to be excluded from this list). DemoDataSeeder
     * (Faker-generated cats, owners, litters, galleries, contact requests,
     * testimonials) is dev/demo content only — never in production.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            ColorSeeder::class,
            ContentPagesSeeder::class,
            SiteSettingsSeeder::class,
            HomeGallerySeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
