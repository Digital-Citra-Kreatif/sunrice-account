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
