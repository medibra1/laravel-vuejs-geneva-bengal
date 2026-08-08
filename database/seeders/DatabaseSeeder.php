<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The first four run in every environment (dev/staging/prod): roles
     * bootstrap, the first super_admin account, the fixed Bengal color
     * reference data, and the real CMS pages/FAQ content (see
     * ContentPagesSeeder — without it, a fresh prod deploy would go live
     * with empty "Informations sur le Bengal"/"Adoption" nav dropdowns and
     * "a-propos"/"contact" 404ing outright). DemoDataSeeder
     * (Faker-generated cats, owners, litters, galleries, contact
     * requests, testimonials) is dev/demo content only — never in
     * production.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            ColorSeeder::class,
            ContentPagesSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
