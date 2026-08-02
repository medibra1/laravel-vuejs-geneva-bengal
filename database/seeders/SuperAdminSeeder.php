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
     * never hardcoded — see .env.example. firstOrCreate keeps this
     * re-runnable in every environment, including production.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => (string) env('SUPER_ADMIN_EMAIL', 'super-admin@example.com')],
            [
                'name' => 'Super Admin',
                'password' => (string) env('SUPER_ADMIN_PASSWORD', 'change-me'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $user->assignRole('super_admin');
    }
}
