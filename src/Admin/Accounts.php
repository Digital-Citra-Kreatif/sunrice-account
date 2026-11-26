<?php

declare(strict_types=1);

namespace Sunrice\Account\Admin;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Panel;

/**
 * Which of the app's users are staff and which are site accounts. They
 * share one table; what tells them apart is whether they can open the
 * admin: staff have a role that does (the super admin's, or one with the
 * panel's access permission) or that permission itself. Everyone else is
 * an account.
 *
 * @api
 */
final class Accounts
{
    /** Whether the user opens the admin. */
    public static function isStaff(Authenticatable $user): bool
    {
        if (! $user instanceof Model) {
            return false;
        }

        return self::staff($user->newQuery())->whereKey($user->getKey())->exists();
    }

    /**
     * Narrows a users query to staff.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function staff(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => self::opensAdmin($q));
    }

    /**
     * Narrows a users query to site accounts: everyone who doesn't open the
     * admin, without those whose personal data was removed.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function accounts(Builder $query): Builder
    {
        return $query
            ->whereNot(fn (Builder $q) => self::opensAdmin($q))
            ->whereNotIn($query->getModel()->getQualifiedKeyName(), Profile::query()->select('user_id')->whereNotNull('anonymized_at'));
    }

    /**
     * Narrows a users query to those Administration → Users lists: staff,
     * and users without a role who never registered on the site (someone
     * just created there, whose roles aren't chosen yet).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function users(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => self::opensAdmin($q)->orWhere(fn (Builder $none) => $none
            ->whereDoesntHave('roles')
            ->whereNotIn($query->getModel()->getQualifiedKeyName(), Profile::query()->select('user_id'))));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function opensAdmin(Builder $query): Builder
    {
        $guard = app(Panel::class)->getGuard();

        return $query
            ->whereHas('roles', fn (Builder $roles) => $roles->where('guard_name', $guard)->whereIn('name', array_keys(AccountSettings::staffRoles())))
            ->orWhereHas('permissions', fn (Builder $permissions) => $permissions->where('guard_name', $guard)->where('name', Panel::ACCESS));
    }
}
