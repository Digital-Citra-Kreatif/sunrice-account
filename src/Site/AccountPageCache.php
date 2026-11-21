<?php

declare(strict_types=1);

namespace Sunrice\Account\Site;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Cms\Site\PageCache;
use Sunrice\Cms\Support\Locales;

/**
 * Account pages in the CMS's full-page cache: never kept, and a visitor
 * signed in to an account always gets fresh pages (a header can show
 * their name), so every kept page is the one a guest sees.
 */
final class AccountPageCache
{
    public static function register(): void
    {
        app(PageCache::class)->skipWhen(fn (Request $request): bool => self::skips($request));
    }

    public static function skips(Request $request): bool
    {
        $route = $request->route();
        if ($route instanceof Route && str_starts_with((string) $route->getName(), 'account.')) {
            return true;
        }
        [, $path] = Locales::split($request->path());
        $prefix = '/'.AccountRoutes::prefix();
        if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
            return true;
        }

        return $request->hasSession() && SiteGuard::get()->check();
    }
}
