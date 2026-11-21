<?php

declare(strict_types=1);

namespace Sunrice\Account;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Emails\EmailSettings;
use Sunrice\Account\MyAccount\Dashboard;
use Sunrice\Account\MyAccount\Fields;
use Sunrice\Account\MyAccount\Navigation;
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

    /**
     * A page in My Account's menu (its route: from Account::routes(), or any).
     * Shown in `position` order (the package's: overview 0, profile 90),
     * while `visible` (if given) says yes.
     *
     * @param  string|Closure(): string  $label
     * @param  (Closure(): bool)|null  $visible
     */
    public function page(string $key, string|Closure $label, string $route, ?Closure $visible = null, int $position = 100, ?string $icon = null): void
    {
        app(Navigation::class)->add($key, $label, $route, $visible, $position, $icon);
    }

    /** Takes a page off the menu (the package's "overview" or "profile" too). */
    public function removePage(string $key): void
    {
        app(Navigation::class)->remove($key);
    }

    /**
     * A card on My Account's dashboard: a view, given `$user` and what `$data` returns.
     *
     * @param  (Closure(Authenticatable): array<string, mixed>)|null  $data
     */
    public function dashboardCard(string $key, string $view, ?Closure $data = null, int $position = 100): void
    {
        app(Dashboard::class)->card($key, $view, $data, $position);
    }

    /**
     * Where /account opens: the URL the closure gives (commerce: the orders),
     * or the dashboard when it gives null.
     *
     * @param  Closure(Authenticatable): ?string  $landing
     */
    public function landingUsing(Closure $landing): void
    {
        app(Dashboard::class)->landingUsing($landing);
    }

    /**
     * Extra fields on the registration form, kept in the profile's meta.
     *
     * @param  Closure(): list<array<string, mixed>>  $fields  see MyAccount\Fields
     */
    public function registrationFields(Closure $fields): void
    {
        app(Fields::class)->addToRegistration($fields);
    }

    /**
     * Extra fields on the profile page, kept in the profile's meta.
     *
     * @param  Closure(): list<array<string, mixed>>  $fields  see MyAccount\Fields
     */
    public function profileFields(Closure $fields): void
    {
        app(Fields::class)->addToProfile($fields);
    }
}
