# Sunrice Account

Site accounts for [Sunrice CMS](https://github.com/Digital-Citra-Kreatif/sunrice): registration,
sign-in (password, emailed code, Google), password reset, email verification, a My Account area
other plugins extend, account emails to members and staff, roles and an account settings page.
Sunrice Commerce builds its customer accounts on it.

Requirements: PHP 8.4+, Laravel 13, Sunrice 0.8 (`sunrice/admin` and `sunrice/cms`).

> Work in progress: see [PLAN.md](PLAN.md) for what's built and what's next.

## Installation

```bash
composer require sunrice/account
php artisan migrate
php artisan sunrice:roles
```

Add the plugin after the CMS in your panel provider:

```php
$panel->userResource(AccountUserResource::class) // Users lists staff only; accounts are in Administration → Accounts
    ->plugin(CmsPlugin::make())->plugin(AccountPlugin::make());
```

Your user model needs spatie/laravel-permission's `HasRoles` (as Sunrice's does) and `Notifiable`.

Developer docs: [docs/](docs/index.md), also shown in the admin under Help → Developer docs.
