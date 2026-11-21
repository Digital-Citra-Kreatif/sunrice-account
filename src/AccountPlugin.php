<?php

declare(strict_types=1);

namespace Sunrice\Account;

use Sunrice\Account\Permissions\AccountPermissions;
use Sunrice\Admin\BasePlugin;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;
use Sunrice\Cms\CmsPlugin;

/**
 * Site accounts, added to the panel after the CMS in the app's panel
 * provider (and before the plugins built on it, such as commerce):
 *
 *     $panel->plugin(CmsPlugin::make())->plugin(AccountPlugin::make());
 *
 * Adds the account permissions, the "member" role new accounts get, and
 * the developer docs.
 */
final class AccountPlugin extends BasePlugin
{
    public const ID = 'account';

    /** The role new accounts get by default (Settings → Accounts). It has no admin permissions. */
    public const MEMBER_ROLE = 'member';

    public function id(): string
    {
        return self::ID;
    }

    /** @return list<string> */
    public function requires(): array
    {
        return [CmsPlugin::ID];
    }

    public function register(Panel $panel): void
    {
        foreach (AccountPermissions::all() as $name => [$label, $group]) {
            $panel->permissions(Permission::make($name, $label, $group));
        }
        $panel->privilegedPermissions('account.settings.*');
        // Created by `sunrice:roles`, without any permission: members never open the admin.
        $panel->defaultRole(self::MEMBER_ROLE, []);
        $panel->docs('Accounts', dirname(__DIR__).'/docs');
    }
}
