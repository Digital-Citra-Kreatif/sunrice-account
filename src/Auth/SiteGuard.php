<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Sunrice\Admin\Panel;

/**
 * The site's session guard (`sunrice-account.auth.guard`, default
 * `account`). Accounts and staff are rows of the same user model: staff
 * sign in to the panel's guard, accounts to this one, on the same user
 * provider, so a site sign-in never opens the admin.
 *
 * @api
 */
final class SiteGuard
{
    public static function name(): string
    {
        $guard = config('sunrice-account.auth.guard');

        return is_string($guard) && $guard !== '' ? $guard : 'account';
    }

    public static function get(): StatefulGuard
    {
        return Auth::guard(self::name());
    }

    /**
     * Whether the site shares the admin's guard (and session). Sign-ins
     * that skip the password (emailed code, Google) then refuse staff, so
     * the admin stays behind its own sign-in.
     */
    public static function sharesPanelGuard(): bool
    {
        return self::name() === app(Panel::class)->getGuard();
    }

    /**
     * Whether the site refuses to sign this user in itself: staff (who can
     * open the admin) while the site shares the admin's guard, so the
     * admin's own sign-in (two-factor, lockouts) can't be skipped.
     */
    public static function refusesStaff(Authenticatable $user): bool
    {
        return self::sharesPanelGuard() && $user instanceof Authorizable && $user->can(Panel::ACCESS);
    }

    /**
     * Defines the guard, on the user provider of the panel's guard, unless
     * config/auth.php defines it already.
     */
    public static function define(Panel $panel): void
    {
        $name = self::name();
        if (config("auth.guards.{$name}") !== null) {
            return;
        }
        $provider = config('auth.guards.'.$panel->getGuard().'.provider');
        config(["auth.guards.{$name}" => ['driver' => 'session', 'provider' => is_string($provider) ? $provider : 'users']]);
    }
}
