<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap super_admin credentials
    |--------------------------------------------------------------------------
    |
    | Used exclusively by SuperAdminSeeder to create the first super_admin
    | account. Read through config() rather than env() directly so the
    | values still resolve correctly when config is cached (php artisan
    | config:cache), which is required in production.
    |
    */

    'email' => env('SUPER_ADMIN_EMAIL', 'super-admin@example.com'),

    'password' => env('SUPER_ADMIN_PASSWORD', 'change-me'),

];
