<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Sunrice\Account\Http\Middleware\Authenticate;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Cms\Support\Locales;

/** @internal Where the sign-in pages send people. */
final class Redirects
{
    /**
     * After signing in: the page a guest was going to (on this site), else
     * the settings page's "after sign-in" path, else My Account.
     */
    public static function afterSignIn(Request $request): string
    {
        $intended = $request->session()->pull(Authenticate::INTENDED);
        if (is_string($intended) && self::onThisSite($request, $intended)) {
            return $intended;
        }
        $path = trim(app(AccountSettings::class)->redirectAfterLogin, '/ ');
        if ($path !== '') {
            return url(Locales::prefix(app()->getLocale()).'/'.$path);
        }

        return AccountRoutes::url('account.dashboard');
    }

    /** The home page in the current language. */
    public static function home(): string
    {
        return url(Locales::prefix(app()->getLocale()) ?: '/');
    }

    private static function onThisSite(Request $request, string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && strcasecmp($host, $request->getHost()) === 0;
    }
}
