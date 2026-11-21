<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;

/** The password broker for account resets (`sunrice-account.auth.passwords`, else the app's default). */
final class Passwords
{
    public static function broker(): PasswordBroker
    {
        $name = config('sunrice-account.auth.passwords');

        return Password::broker(is_string($name) && $name !== '' ? $name : null);
    }
}
