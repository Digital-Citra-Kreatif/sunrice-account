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

        return AccountRoutes::url('sunrice.account.dashboard');
    }

    /**
     * A sign-in or registration page opened with `?return=` (a path or a URL
     * of this site): that is where signing in leads back to. Anything else
     * is ignored, and what a guarded page already noted stays.
     */
    public static function remember(Request $request): void
    {
        $return = $request->query('return');
        $target = is_string($return) && $return !== '' ? self::siteUrl($request, $return) : null;
        if ($target !== null && $request->hasSession()) {
            $request->session()->put(Authenticate::INTENDED, $target);
        }
    }

    /** Where the visitor was going, when a page noted it (not taken out of the session). */
    public static function intended(Request $request): ?string
    {
        $intended = $request->hasSession() ? $request->session()->get(Authenticate::INTENDED) : null;

        return is_string($intended) && self::onThisSite($request, $intended) ? $intended : null;
    }

    /** The home page in the current language. */
    public static function home(): string
    {
        return url(Locales::prefix(app()->getLocale()) ?: '/');
    }

    /**
     * Whether `$url` is an address on this site (a full URL with this
     * host): no backslashes, control characters or user info, which
     * browsers read differently from parse_url() (`https://evil\@site/`).
     */
    public static function onThisSite(Request $request, string $url): bool
    {
        if (preg_match('/[\\\\\x00-\x20\x7f]/', $url) === 1) {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        return in_array($scheme, ['http', 'https'], true) && is_string($host) && strcasecmp($host, $request->getHost()) === 0;
    }

    /** A path on this site (`/checkout`) or a URL of it as a full URL; null for anything else. */
    public static function siteUrl(Request $request, string $target): ?string
    {
        $target = trim($target);
        if (str_starts_with($target, '/') && ! str_starts_with($target, '//') && preg_match('/[\\\\\x00-\x1f\x7f]/', $target) !== 1) {
            return url($target);
        }

        return self::onThisSite($request, $target) ? $target : null;
    }
}
