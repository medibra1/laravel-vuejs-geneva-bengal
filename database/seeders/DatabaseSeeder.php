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
     * Always run, in every environment (dev/staging/prod): roles bootstrap
     * and the first super_admin account. ColorSeeder joins this order once
     * the `colors` table lands in Phase 1, DemoDataSeeder once it exists,
     * guarded by `! app()->isProduction()`.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
