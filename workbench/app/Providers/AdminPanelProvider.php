<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Sunrice\Account\AccountPlugin;
use Sunrice\Account\Admin\AccountUserResource;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;
use Sunrice\Cms\CmsPlugin;

/** The panel of the tests and of the workbench site: the CMS, and accounts after it. */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Users lists staff only: site accounts are in Administration → Accounts.
        return $panel->path('admin')->userResource(AccountUserResource::class)->plugin(CmsPlugin::make())->plugin(AccountPlugin::make());
    }
}
