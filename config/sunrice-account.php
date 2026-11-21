<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sign-in
    |--------------------------------------------------------------------------
    |
    | Accounts sign in on the site with their own session guard, on the same
    | user provider (and table) as the admin panel's guard, so a site sign-in
    | never opens the admin. The guard is defined automatically when
    | config/auth.php doesn't define it. Set it to the panel's guard (`web`)
    | to share one session between the site and the admin.
    |
    | `passwords` is the password broker (config/auth.php `passwords`) used
    | for password resets; null for the app's default.
    |
    */

    'auth' => [
        'guard' => 'account',
        'passwords' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings defaults
    |--------------------------------------------------------------------------
    |
    | What the account settings are until they are saved in the admin.
    |
    */

    'defaults' => [
        'general' => [
            // Visitors can create an account.
            'registration' => true,
            // New accounts confirm their email address before they can sign in.
            'verify_email' => true,
            // Roles given to new accounts ("member" is created by `sunrice:roles`).
            'default_roles' => ['member'],
        ],
    ],

];
