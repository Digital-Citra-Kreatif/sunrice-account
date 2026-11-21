<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\Settings\AccountSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * `account.verified`: signed-in accounts with a verified email, while
 * Settings → Accounts requires verification (after `account.auth`).
 * Others are asked to verify first.
 */
final class EnsureEmailVerified
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = SiteGuard::get()->user();
        if ($user === null || ! app(AccountSettings::class)->boolean('general.verify_email', true) || Verification::verified($user)) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            abort(403);
        }

        return redirect()->to(AccountRoutes::url('account.verification.notice'))->with('account.status', __('account::auth.verify.required'));
    }
}
