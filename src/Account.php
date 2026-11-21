<?php

declare(strict_types=1);

namespace Sunrice\Account;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Emails\EmailSettings;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\Settings\AccountSettings;

/**
 * The account package's public API (also the `Account` facade). Plugins
 * built on accounts (commerce…) use these methods.
 *
 * @api
 */
final class Account
{
    /** The settings (Settings → Accounts) over their config defaults. */
    public function settings(): AccountSettings
    {
        return app(AccountSettings::class);
    }

    /** The account emails: the package's and plugins' (`->register(new EmailDefinition(...))`). */
    public function emails(): Emails
    {
        return app(Emails::class);
    }

    /** An email's settings: whether it's on, its subjects, its staff recipients. */
    public function emailSettings(): EmailSettings
    {
        return app(EmailSettings::class);
    }

    /** The name of the site's session guard. */
    public function guard(): string
    {
        return SiteGuard::name();
    }

    /** The account signed in on the site, or null. */
    public function user(): ?Authenticatable
    {
        return SiteGuard::get()->user();
    }

    /** The profile of a user (created on first need). */
    public function profile(Authenticatable $user): Profile
    {
        return Profile::forUser($user);
    }

    /**
     * Pages under the account prefix (/account/orders…), in every language
     * of the site. They are for signed-in accounts (`account.auth`) unless
     * $signedIn is false. Name them with the suffix the closure receives:
     *
     *     Account::routes(function (string $suffix) {
     *         Route::get('orders', OrdersController::class)->name('shop.account.orders'.$suffix);
     *     });
     *
     * @param  Closure(string): void  $routes
     */
    public function routes(Closure $routes, bool $signedIn = true): void
    {
        app(AccountRoutes::class)->extend($routes, $signedIn);
    }

    /**
     * An account page's URL in the current (or given) language:
     * Account::url('account.dashboard').
     *
     * @param  array<string, mixed>  $parameters
     */
    public function url(string $name, array $parameters = [], ?string $locale = null): string
    {
        return AccountRoutes::url($name, $parameters, $locale);
    }
}
