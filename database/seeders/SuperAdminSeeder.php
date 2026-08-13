<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Bootstrap the first super_admin account, so subsequent admins can be
     * created through the admin/users UI instead of the console.
     *
     * Credentials come from env (SUPER_ADMIN_EMAIL/SUPER_ADMIN_PASSWORD),
     * never hardcoded — see .env.example.
     *
     * Idempotent (firstOrCreate by email): safe to re-run in any
     * environment, including production.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => config('super_admin.email')],
            [
                'name' => 'Super Admin',
                'password' => config('super_admin.password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $user->assignRole('super_admin');
    }
}
