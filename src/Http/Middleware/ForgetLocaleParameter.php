<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account routes in other languages live under /{locale}; the language
 * is already set (SetSiteLocale), so controllers don't get it as a
 * parameter.
 */
final class ForgetLocaleParameter
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        if ($route instanceof Route) {
            $route->forgetParameter('locale');
        }

        return $next($request);
    }
}
