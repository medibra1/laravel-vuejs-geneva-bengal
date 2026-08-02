<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the two admin roles. Idempotent: safe to re-run in any environment.
     *
     * Permissions are not enumerated here — access is split at the
     * controller/route level (super_admin-only routes) plus the
     * Gate::before bypass for super_admin, per CLAUDE.md.
     */
    public function run(): void
    {
        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('admin', 'web');
    }
}
