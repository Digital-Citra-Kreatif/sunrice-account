<?php

declare(strict_types=1);

namespace Sunrice\Account\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Sunrice\Account\Settings\AccountSettings settings()
 * @method static \Sunrice\Account\Emails\Emails emails()
 * @method static \Sunrice\Account\Emails\EmailSettings emailSettings()
 * @method static string guard()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null user()
 * @method static \Sunrice\Account\Profiles\Models\Profile profile(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static void routes(\Closure(string): void $routes, bool $signedIn = true)
 * @method static string url(string $name, array<string, mixed> $parameters = [], ?string $locale = null)
 *
 * @see \Sunrice\Account\Account
 *
 * @api
 */
final class Account extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sunrice\Account\Account::class;
    }
}
