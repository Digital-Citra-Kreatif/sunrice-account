<?php

declare(strict_types=1);

namespace Sunrice\Account\Listeners;

use Illuminate\Support\Facades\Artisan;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Events\SettingsSaved;

/**
 * The account routes read the account address (Settings → Accounts →
 * Account address) when they're registered: when it changes, cached
 * routes would keep the old one, so the route cache is cleared (run
 * `php artisan route:cache` again after deploying, as usual).
 *
 * @internal
 */
final class ClearRouteCache
{
    public function handle(SettingsSaved $event): void
    {
        if ($event->section === AccountSettings::KEY && in_array('path', $event->changed, true) && app()->routesAreCached()) {
            Artisan::call('route:clear');
        }
    }
}
