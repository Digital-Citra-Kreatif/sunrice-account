<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Support\Facades\Notification;
use Sunrice\Account\Auth\Passwords;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Notifications\ResetPasswordNotification;

/**
 * Emails a password reset link to the account with this address, if there
 * is one. Nothing tells the visitor whether there was.
 */
final class SendPasswordResetLink
{
    public function handle(string $email, string $locale): void
    {
        Passwords::broker()->sendResetLink(
            ['email' => Users::normalizeEmail($email)],
            function (mixed $user, string $token) use ($locale): void {
                Notification::send($user, new ResetPasswordNotification($token, $locale));
            },
        );
    }
}
