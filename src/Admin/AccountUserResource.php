<?php

declare(strict_types=1);

namespace Sunrice\Account\Admin;

use Illuminate\Database\Eloquent\Builder;
use Sunrice\Admin\Users\UserResource;

/**
 * Administration → Users with staff only: site accounts are listed in
 * Administration → Accounts instead. A plugin can't change the panel's
 * Users screen, so the app's panel provider chooses this one:
 *
 *     $panel->userResource(AccountUserResource::class)->plugin(CmsPlugin::make())->plugin(AccountPlugin::make());
 *
 * Without it, Users lists site accounts too. Extend it to change the form
 * or the list (see the panel's docs, "Users and roles").
 *
 * @api
 */
class AccountUserResource extends UserResource
{
    public static function query(): Builder
    {
        return Accounts::users(parent::query());
    }
}
