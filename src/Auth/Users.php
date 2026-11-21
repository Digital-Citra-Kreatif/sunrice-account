<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Sunrice\Admin\Panel;

/**
 * The app's users: the panel's user model, which accounts share with
 * staff (an Eloquent model that signs in and, with spatie's HasRoles,
 * has roles).
 *
 * @api
 */
final class Users
{
    /** @return class-string<Model&Authenticatable> */
    public static function model(): string
    {
        $model = app(Panel::class)->getUserModel();
        if (! is_a($model, Authenticatable::class, true)) {
            throw new LogicException("The panel's user model ({$model}) isn't Authenticatable.");
        }

        return $model;
    }

    /** The user with this email address (compared in lower case), or null. */
    public static function findByEmail(string $email): (Model&Authenticatable)|null
    {
        $user = self::model()::query()->where('email', self::normalizeEmail($email))->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
