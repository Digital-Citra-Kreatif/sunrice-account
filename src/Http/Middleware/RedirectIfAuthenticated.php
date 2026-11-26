<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Http\Controllers\Auth\Redirects;
use Sunrice\Account\Routing\AccountRoutes;
use Symfony\Component\HttpFoundation\Response;

/** `sunrice.account.guest`: pages for guests only (sign in, register…); signed-in accounts go to My Account, or to `?return=`, which guests go back to once signed in. */
final class RedirectIfAuthenticated
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (SiteGuard::get()->check()) {
            // Already signed in: on to where the link was leading (a "Sign in to continue" link), else My Account.
            $return = $request->query('return');
            $target = is_string($return) && $return !== '' ? Redirects::siteUrl($request, $return) : null;

            return redirect($target ?? AccountRoutes::url('sunrice.account.dashboard'));
        }
        Redirects::remember($request);

        return $next($request);
    }
}
