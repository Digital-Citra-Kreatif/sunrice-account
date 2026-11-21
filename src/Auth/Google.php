<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Sunrice\Account\Settings\AccountSettings;

/**
 * Sign in with Google (Settings → Accounts → Sign-in), with laravel/socialite:
 * the credentials of config/services.php's `google`, else
 * `sunrice-account.google`, and one fixed callback URL.
 *
 * @api
 */
final class Google
{
    public static function available(): bool
    {
        return app(AccountSettings::class)->googleLogin && self::configured();
    }

    public static function configured(): bool
    {
        return self::credential('client_id') !== '' && self::credential('client_secret') !== '';
    }

    public static function driver(): Provider
    {
        if (config('services.google.client_id') === null) {
            config(['services.google' => [
                'client_id' => self::credential('client_id'),
                'client_secret' => self::credential('client_secret'),
                'redirect' => route('sunrice.account.google.callback'),
            ]]);
        }
        $driver = Socialite::driver('google');
        if ($driver instanceof AbstractProvider) {
            $driver->redirectUrl(route('sunrice.account.google.callback'));
        }

        return $driver;
    }

    private static function credential(string $key): string
    {
        $value = config("services.google.{$key}") ?? config("sunrice-account.google.{$key}");

        return is_string($value) ? trim($value) : '';
    }
}
