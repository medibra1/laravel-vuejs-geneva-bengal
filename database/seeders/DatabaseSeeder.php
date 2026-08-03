<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Always run, in every environment (dev/staging/prod): roles bootstrap,
     * the first super_admin account, and the fixed Bengal color reference
     * data. DemoDataSeeder joins this order once it exists, guarded by
     * `! app()->isProduction()`.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            ColorSeeder::class,
        ]);
    }
}
