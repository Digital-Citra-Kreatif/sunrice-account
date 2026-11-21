<?php

declare(strict_types=1);

namespace Sunrice\Account\View;

use InvalidArgumentException;

/**
 * Which views account pages use. The site layout they extend is the
 * site's (sunrice-account.layout, by default the CMS's `cms.layout`) when
 * it exists, else the package's minimal one; both take a `content`
 * section and a `seoTitle`. Any account view can be replaced by the app's
 * copy in resources/views/vendor/account/ (Laravel's place for a package's
 * views: `auth/login.blade.php` for `account::auth.login`).
 *
 * @api
 */
final class Layout
{
    /**
     * The site layout account pages extend.
     *
     * @return view-string
     */
    public static function site(): string
    {
        $layout = config('sunrice-account.layout');
        if (is_string($layout) && $layout !== '' && view()->exists($layout)) {
            return $layout;
        }

        return self::view('layouts.site');
    }

    /**
     * An account view: the app's copy in resources/views/vendor/account/
     * when it has one, else the package's (Laravel looks there first).
     *
     * @return view-string
     */
    public static function view(string $name): string
    {
        $package = "account::{$name}";
        if (view()->exists($package)) {
            return $package;
        }

        throw new InvalidArgumentException("There is no account view \"{$name}\".");
    }
}
