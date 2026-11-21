<?php

declare(strict_types=1);

namespace Sunrice\Account;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Support\PackageConfig;

/**
 * Site accounts: the `account` guard, the settings, the profiles table.
 * What the package adds to the admin is in AccountPlugin.
 */
final class AccountServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sunrice-account')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        // Keys added in later versions reach apps that published an older copy of the config file.
        PackageConfig::merge($this->app, __DIR__.'/../config/sunrice-account.php', 'sunrice-account');

        $this->app->singleton(Account::class);
        // Read once per request (and per queued job).
        $this->app->scoped(AccountSettings::class);

        // The guard needs the panel's guard, known once the app's panel provider has set the panel up.
        $this->app->afterResolving(Panel::class, function (Panel $panel): void {
            SiteGuard::define($panel);
        });
    }
}
