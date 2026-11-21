<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Admin\Security\AccountGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * `sunrice.account.auth`: signed-in accounts only. Guests go to the sign-in page
 * and come back to where they were going. A session of a locked account,
 * or one that signed in before the account was signed out everywhere (a
 * password change or reset does that too), is signed out.
 */
final class Authenticate
{
    public function __construct(private AccountGuard $accounts) {}

    /** Session key of the page a guest was going to (read after signing in). */
    public const INTENDED = 'account.intended';

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = SiteGuard::get();
        $user = $guard->user();
        if ($user !== null && $this->accounts->sessionAllowed($request, $user)) {
            return $next($request);
        }
        if ($user !== null) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect(AccountRoutes::url('sunrice.account.login'))->with('account.status', __('account::auth.login.signed_out'));
        }
        if ($request->expectsJson() || ! Route::has('sunrice.account.login')) {
            abort(401);
        }
        if ($request->isMethod('GET')) {
            $request->session()->put(self::INTENDED, $request->fullUrl());
        }

        return redirect(AccountRoutes::url('sunrice.account.login'));
    }
}
