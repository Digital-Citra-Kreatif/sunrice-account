<?php

declare(strict_types=1);

namespace Sunrice\Account\Listeners;

use Sunrice\Account\Events\SignedIn;
use Sunrice\Admin\Security\AccountGuard;

/**
 * Notes when this session signed in, as the admin's sign-in does: "Sign
 * out everywhere" (and a lock) ends the sessions that signed in before it,
 * on the site too (account.auth checks).
 */
final class MarkSessionStart
{
    public function handle(SignedIn $event): void
    {
        $request = request();
        if ($request->hasSession()) {
            $request->session()->put(AccountGuard::LOGIN_AT, now()->getTimestamp());
        }
    }
}
