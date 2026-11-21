# Accounts

`sunrice/account` gives the site accounts of its own, kept apart from the admin: people register
and sign in on the site, and a site sign-in never opens the admin.

## The guard

Accounts sign in on the `account` session guard (`sunrice-account.auth.guard`). Unless
`config/auth.php` defines that guard, it is added on the user provider of the panel's guard
(`$panel->guard()`, `web` by default): accounts and staff are rows of the same users table, with
separate sessions. Members have no admin permissions, and the admin requires `sunrice.access`, so
they never get in.

Setting `sunrice-account.auth.guard` to the panel's guard shares one session between the site and
the admin. `SiteGuard::sharesPanelGuard()` tells you when that's the case.

```php
use Sunrice\Account\Facades\Account;

Account::guard();          // "account"
Account::user();           // the account signed in on the site, or null
Account::profile($user);   // its Profile (created on first need)
```

## Settings

`Account::settings()` reads the account settings: what was saved in the admin over
`config('sunrice-account.defaults')`.

```php
Account::settings()->boolean('general.registration');
Account::settings()->strings('general.default_roles');   // ['member']
Account::settings()->section('general');
Account::settings()->saveSection('general', ['registration' => false]);
```

Plugins built on accounts keep their own sections in the same row, under their own key
(`saveSection('shop', [...])`). Saving a section only replaces the keys it's given.

| Key | Default | |
| --- | --- | --- |
| `general.registration` | `true` | Visitors can create an account |
| `general.verify_email` | `true` | New accounts confirm their email before signing in |
| `general.default_roles` | `['member']` | Roles new accounts get |
| `general.path` | `null` | The account address prefix, over `paths.prefix` |

## Profiles

`account_profiles` holds what an account has beside the users table: `phone`, `locale`,
`accepts_marketing` and `marketing_consent_at`, `password_set` (false for accounts made without a
password until they set one), `deletion_requested_at`, and `meta`, where plugins keep what they
add, under their own key:

```php
$profile = Account::profile($user);
$profile->metaValue('shop.tax_id');
```

## Roles and permissions

| | |
| --- | --- |
| `member` role | Given to new accounts. No admin permissions. Created by `php artisan sunrice:roles` |
| `account.settings.manage` | Edit the account settings (a privileged permission) |

## Pages and routes

Account pages live under the account prefix: `/account` (`sunrice-account.paths.prefix`, or the
settings page's `general.path`), in every language of the site, like the CMS's pages: `/account`
in the language at the plain addresses, `/en/account` in English. Route names are `account.*`,
with `.locale` for the other languages, so build addresses with `Account::url()`:

```php
Account::url('account.dashboard');                 // in the current language
Account::url('account.dashboard', locale: 'en');   // http://example.test/en/account
```

Add your plugin's pages under the prefix with `Account::routes()`, from your service provider or
plugin. They're for signed-in accounts unless you pass `signedIn: false`, they're `noindex`, and
the page cache never keeps them:

```php
use Illuminate\Support\Facades\Route;
use Sunrice\Account\Facades\Account;

Account::routes(function (string $suffix) {
    Route::get('orders', OrdersController::class)->name('shop.account.orders'.$suffix);
});
```

Routes of your own elsewhere can use `AccountRoutes::localized(fn (string $suffix) => ...)` for the
same languages, and the middleware:

| Middleware | |
| --- | --- |
| `account.auth` | Signed-in accounts only. Guests go to the sign-in page (`account.login`) and come back |
| `account.guest` | Guests only (sign in, register); signed-in accounts go to My Account |
| `account.noindex` | `X-Robots-Tag: noindex`, `Cache-Control: private, no-store`, and the layout's robots tag |

A visitor signed in to an account always gets fresh pages from the CMS's page cache.

## Views and layout

Account pages extend the site's layout: `sunrice-account.layout` (by default the CMS's
`cms.layout`, `resources/views/cms/layout.blade.php`), or the package's minimal one when the site
has none. Like it, they fill `@section('content')` and pass `$seoTitle`.

The signed-in area's own layout is `account::layout`; your pages extend it and fill
`@section('account')`:

```blade
@extends(\Sunrice\Account\View\Layout::view('layout'))

@section('account')
    <h1>Orders</h1>
@endsection
```

To change an account view, publish the views (`php artisan vendor:publish
--tag=sunrice-account-views`) and edit your copy in `resources/views/account/`: an app's
`account.dashboard` is used before the package's `account::dashboard` (`Layout::view()`).
