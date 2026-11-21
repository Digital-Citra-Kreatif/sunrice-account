<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Panel;

/** Gives a new account the default roles (Settings → Accounts), on the panel's guard. */
final class AssignDefaultRoles
{
    public function __construct(private AccountSettings $settings, private Panel $panel) {}

    public function handle(Authenticatable $user): void
    {
        // The user model needs spatie's HasRoles, as the panel's does (see the README).
        if (! method_exists($user, 'assignRole')) {
            return;
        }
        foreach ($this->settings->strings('general.default_roles') as $role) {
            $user->assignRole(Role::findOrCreate($role, $this->panel->getGuard()));
        }
    }
}
