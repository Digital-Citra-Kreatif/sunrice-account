<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Routing\AccountRoutes;
use Symfony\Component\HttpFoundation\Response;

/**
 * `account.auth`: signed-in accounts only. Guests go to the sign-in page
 * and come back to where they were going.
 */
final class Authenticate
{
    /** Session key of the page a guest was going to (read after signing in). */
    public const INTENDED = 'account.intended';

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (SiteGuard::get()->check()) {
            return $next($request);
        }
        if ($request->expectsJson() || ! Route::has('account.login')) {
            abort(401);
        }
        if ($request->isMethod('GET')) {
            $request->session()->put(self::INTENDED, $request->fullUrl());
        }

        return redirect(AccountRoutes::url('account.login'));
    }
}
