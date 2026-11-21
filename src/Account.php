<?php

declare(strict_types=1);

namespace Sunrice\Account;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Emails\EmailSettings;
use Sunrice\Account\Http\Controllers\Auth\Redirects;
use Sunrice\Account\Http\Middleware\Authenticate;
use Sunrice\Account\MyAccount\Dashboard;
use Sunrice\Account\MyAccount\Fields;
use Sunrice\Account\MyAccount\Navigation;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Panel;

/**
 * The account package's public API (also the `Account` facade). Plugins
 * built on accounts (commerce…) use these methods.
 *
 * @api
 */
final class Account
{
    private ?string $settingsOwner = null;

    /** The settings (Settings → Accounts), as saved (each property's default until then). */
    public function settings(): AccountSettings
    {
        return app(AccountSettings::class);
    }

    /**
     * A plugin shows the account settings on a page of its own (commerce:
     * Settings → Store → Accounts), so Settings → Accounts leaves the menu
     * and there are never two places for them. Call it in the plugin's
     * register(). The plugin draws the form with SettingsFields (see the docs).
     */
    public function settingsShownBy(string $plugin): void
    {
        $this->settingsOwner = $plugin;
    }

    /** The plugin showing the account settings, or null when Settings → Accounts does. */
    public function settingsOwner(): ?string
    {
        return $this->settingsOwner;
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

    /**
     * The names of an account's roles (the panel's guard's), sorted: what
     * a plugin checks to let some accounts in (members-only pages).
     *
     * @return list<string>
     */
    public function roles(Authenticatable $user): array
    {
        if (! $user instanceof Model) {
            return [];
        }
        $assigned = DB::table(self::permissionConfig('table_names.model_has_roles', 'model_has_roles'))
            ->where(self::permissionConfig('column_names.model_morph_key', 'model_id'), $user->getKey())
            ->where('model_type', $user->getMorphClass())
            ->select(self::permissionConfig('column_names.role_pivot_key', 'role_id'));
        $names = Role::query()->where('guard_name', app(Panel::class)->getGuard())
            ->whereIn((new Role)->getKeyName(), $assigned)->orderBy('name')->pluck('name')->all();

        return array_values(array_filter($names, is_string(...)));
    }

    /**
     * Send a guest to the sign-in page, and back to `$returnTo` (a path
     * like `/checkout` or a URL of this site; by default, or for anything
     * else, the page they asked for) once they've signed in.
     */
    public function redirectToSignIn(?string $returnTo = null): RedirectResponse
    {
        $request = request();
        if ($request->hasSession()) {
            // Only an address on this site: a plugin passing on a visitor's ?return= can't make it an open redirect.
            $target = $returnTo === null ? null : Redirects::siteUrl($request, $returnTo);
            $request->session()->put(Authenticate::INTENDED, $target ?? $request->fullUrl());
        }

        return redirect(AccountRoutes::url('sunrice.account.login'));
    }

    /** The profile of a user (created on first need). */
    public function profile(Authenticatable $user): Profile
    {
        return Profile::forUser($user);
    }

    /**
     * Pages under the account prefix (/account/orders…), in every language
     * of the site. They are for signed-in accounts (`sunrice.account.auth`) unless
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
     * Account::url('sunrice.account.dashboard').
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

    /** A table or column name of spatie/laravel-permission's config. */
    private static function permissionConfig(string $key, string $default): string
    {
        $name = config("permission.{$key}");

        return is_string($name) && $name !== '' ? $name : $default;
    }
}
