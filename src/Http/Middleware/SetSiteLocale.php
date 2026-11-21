<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sunrice\Cms\Support\Locales;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account pages are in the language of their address, as the CMS's pages
 * are: /en/account in English, /account in the language at the plain
 * addresses.
 */
final class SetSiteLocale
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        [$locale] = Locales::split($request->path());
        app()->setLocale($locale);

        return $next($request);
    }
}
