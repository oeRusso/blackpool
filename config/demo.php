<?php

use Database\Seeders\UserSeeder;

return [

    /*
    | Public portfolio demo: demo accounts cannot change their credentials or
    | security settings, the login page offers one-click demo logins, and the
    | content is reset every night by `php artisan demo:reset`.
    */
    'enabled' => (bool) env('DEMO_MODE', false),

    'password' => UserSeeder::DEMO_PASSWORD,

    'accounts' => array_map(
        fn (array $user): string => $user['email'],
        UserSeeder::DEMO_USERS,
    ),

    // Routes that would let a visitor lock others out of a demo account.
    'protected_routes' => [
        'profile.update',
        'profile.destroy',
        'user-password.update',
        'password.email',
        'password.update',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.regenerate-recovery-codes',
        'passkey.store',
        'passkey.destroy',
    ],

];
