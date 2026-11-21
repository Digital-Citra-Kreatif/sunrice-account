<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Notifications\VerifyEmailNotification;

/** Emails an unverified account its verification link: at most once a minute per account. */
final class SendVerificationLink
{
    /** @return bool whether a link was sent */
    public function handle(Authenticatable $user, string $locale): bool
    {
        if (Verification::verified($user)) {
            return false;
        }
        $id = $user->getAuthIdentifier();
        $key = 'account-verification-mail:'.(is_scalar($id) ? (string) $id : '');
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return false;
        }
        RateLimiter::hit($key, 60);
        Notification::send($user, new VerifyEmailNotification($locale));

        return true;
    }
}
