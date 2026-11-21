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
    | Addresses
    |--------------------------------------------------------------------------
    |
    | The account pages live under `prefix` (/account, and /en/account in the
    | site's other languages); the settings page can give the prefix another
    | value (general.path). The other paths are under the prefix.
    |
    | `layout` is the site layout account pages extend: by default the CMS's
    | site layout (resources/views/cms/layout.blade.php); when it doesn't
    | exist, the package's minimal one. An app overrides any account view by
    | publishing it to resources/views/account/.
    |
    */

    'paths' => [
        'prefix' => 'account',
        'login' => 'login',
        'register' => 'register',
        'forgot_password' => 'forgot-password',
        'reset_password' => 'reset-password',
        'verify' => 'verify',
    ],

    'layout' => 'cms.layout',

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
            // The account address prefix, when set in the settings page (null: paths.prefix).
            'path' => null,
        ],
    ],

];
