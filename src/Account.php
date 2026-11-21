<?php

declare(strict_types=1);

namespace Sunrice\Account;

use Illuminate\Contracts\Auth\Authenticatable;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Profiles\Models\Profile;
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
}
