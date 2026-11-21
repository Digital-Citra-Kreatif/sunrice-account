<?php

declare(strict_types=1);

namespace Sunrice\Account\Permissions;

/** The permissions the account plugin declares, in Users → Roles under "Accounts". */
final class AccountPermissions
{
    /** @return array<string, array{0: string, 1: string}> name => [label, group] */
    public static function all(): array
    {
        return [
            'account.settings.manage' => ['Edit account settings', 'Accounts'],
        ];
    }
}
