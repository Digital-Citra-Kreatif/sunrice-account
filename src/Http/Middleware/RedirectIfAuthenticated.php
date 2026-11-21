<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Routing\AccountRoutes;
use Symfony\Component\HttpFoundation\Response;

/** `account.guest`: pages for guests only (sign in, register…); signed-in accounts go to My Account. */
final class RedirectIfAuthenticated
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (SiteGuard::get()->check()) {
            return redirect(AccountRoutes::url('account.dashboard'));
        }

        return $next($request);
    }
}
