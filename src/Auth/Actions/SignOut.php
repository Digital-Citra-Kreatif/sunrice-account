<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Events\SignedOut;

/**
 * Signs the account out of the site. With the site's own guard (the
 * default) an admin session on the panel's guard stays; while the site
 * shares the panel's guard (sunrice-account.auth.guard set to it) they're
 * one session, and this ends both.
 */
final class SignOut
{
    public function handle(): void
    {
        $guard = SiteGuard::get();
        $user = $guard->user();
        $guard->logout();
        if ($user !== null) {
            event(new SignedOut($user));
        }
    }
}
