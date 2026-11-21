<?php

declare(strict_types=1);

namespace Sunrice\Account;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Events\DeletionRequested;
use Sunrice\Account\Events\Registered;
use Sunrice\Account\Events\SignedIn;
use Sunrice\Account\Events\Verified;
use Sunrice\Account\Http\Middleware;
use Sunrice\Account\Listeners\ClearRouteCache;
use Sunrice\Account\Listeners\MarkSessionStart;
use Sunrice\Account\Listeners\NotifyStaffOfDeletionRequest;
use Sunrice\Account\Listeners\NotifyStaffOfRegistration;
use Sunrice\Account\Listeners\SendWelcomeEmail;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\Site\AccountPageCache;
use Sunrice\Admin\Events\SettingsSaved;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Support\PackageConfig;

/**
 * Site accounts: the `account` guard, the settings, the profiles table,
 * the account pages and their routes. What the package adds to the admin
 * is in AccountPlugin.
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
        $this->app->singleton(AccountRoutes::class);
        $this->app->singleton(Emails::class);
        $this->app->singleton(MyAccount\Navigation::class);
        $this->app->singleton(MyAccount\Dashboard::class);
        $this->app->singleton(MyAccount\Fields::class);

        // The guard needs the panel's guard, known once the app's panel provider has set the panel up.
        $this->app->afterResolving(Panel::class, function (Panel $panel): void {
            SiteGuard::define($panel);
        });
    }

    public function packageBooted(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'account');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'account');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/account')], 'sunrice-account-views');
        $this->publishes([__DIR__.'/../lang' => lang_path('vendor/account')], 'sunrice-account-translations');

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('sunrice.account.auth', Middleware\Authenticate::class);
        $router->aliasMiddleware('sunrice.account.guest', Middleware\RedirectIfAuthenticated::class);
        $router->aliasMiddleware('sunrice.account.noindex', Middleware\NoIndex::class);
        $router->aliasMiddleware('sunrice.account.verified', Middleware\EnsureEmailVerified::class);

        // After the app has booted: the site's saved languages and the account path apply, and
        // plugins have added their pages. The CMS's catch-all is a fallback route, so these win.
        $this->app->booted(function (): void {
            if (! $this->app->routesAreCached()) {
                $this->app->make(AccountRoutes::class)->register();
            }
        });

        AccountPageCache::register();

        Event::listen([Registered::class, Verified::class], SendWelcomeEmail::class);
        Event::listen(Registered::class, NotifyStaffOfRegistration::class);
        Event::listen(DeletionRequested::class, NotifyStaffOfDeletionRequest::class);
        Event::listen(SettingsSaved::class, ClearRouteCache::class);
        Event::listen(SignedIn::class, MarkSessionStart::class);

        $navigation = $this->app->make(MyAccount\Navigation::class);
        $navigation->add('announcement', fn (): string => $this->app->make(MyAccount\Announcement::class)->title(), 'sunrice.account.announcement',
            fn (): bool => $this->app->make(MyAccount\Announcement::class)->position() === 'tab', -10);
        $navigation->add('overview', fn (): string => (string) __('account::account.dashboard.title'), 'sunrice.account.overview', position: 0);
        $navigation->add('profile', fn (): string => (string) __('account::account.profile.title'), 'sunrice.account.profile', position: 90);
    }
}
