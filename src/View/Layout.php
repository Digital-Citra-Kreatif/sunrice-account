<?php

declare(strict_types=1);

namespace Sunrice\Account\View;

/**
 * Which views account pages use. The site layout they extend is the
 * site's (sunrice-account.layout, by default the CMS's `cms.layout`) when
 * it exists, else the package's minimal one; both take a `content`
 * section and a `seoTitle`. Any account view can be replaced by the app's
 * copy in resources/views/account/ (`account.login` for `account::login`).
 *
 * @api
 */
final class Layout
{
    /** The site layout account pages extend. */
    public static function site(): string
    {
        $layout = config('sunrice-account.layout');

        return is_string($layout) && $layout !== '' && view()->exists($layout) ? $layout : self::view('layouts.site');
    }

    /** The app's copy of an account view when it has one, else the package's. */
    public static function view(string $name): string
    {
        return view()->exists("account.{$name}") ? "account.{$name}" : "account::{$name}";
    }
}
