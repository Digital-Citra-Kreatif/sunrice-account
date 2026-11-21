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
    | site's other languages); Settings → Accounts can give the prefix another
    | value (Account address). The other paths are under the prefix. The other
    | account settings are in Settings → Accounts (AccountSettings).
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
    | Sign in with Google
    |--------------------------------------------------------------------------
    |
    | Used when config/services.php has no `google` block of its own. The
    | callback URL to give Google is /account/login/google/callback.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

];
