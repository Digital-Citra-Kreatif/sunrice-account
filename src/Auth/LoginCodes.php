<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Spatie\OneTimePasswords\Actions\ConsumeOneTimePasswordAction;
use Spatie\OneTimePasswords\Actions\CreateOneTimePasswordAction;
use Spatie\OneTimePasswords\Enums\ConsumeOneTimePasswordResult;
use Sunrice\Account\Notifications\LoginCodeNotification;
use Sunrice\Account\Settings\AccountSettings;

/**
 * Emailed sign-in codes (`general.login_code`), with
 * spatie/laravel-one-time-passwords: the user model uses its
 * `HasOneTimePasswords` trait, and its `one_time_passwords` table exists.
 * A code is 6 digits, valid for `general.code_minutes`, usable once, from
 * the browser that asked (same IP and user agent); a new code replaces
 * the previous one; 5 tries a minute per account (and the controller's own
 * throttle on top).
 *
 * The package's actions are used directly, never its
 * attemptLoginUsingOneTimePassword(): that signs in on the default guard,
 * the admin's. Its own notification isn't used either: the code goes out
 * as an account email.
 *
 * @api
 */
final class LoginCodes
{
    public function __construct(
        private AccountSettings $settings,
        private CreateOneTimePasswordAction $create,
        private ConsumeOneTimePasswordAction $consume,
    ) {}

    public function available(): bool
    {
        return $this->settings->boolean('general.login_code');
    }

    /** Creates a code for the user (replacing an earlier one) and emails it. */
    public function send(Authenticatable $user, Request $request, string $locale): void
    {
        $minutes = $this->minutes();
        $code = $this->create->execute($user, $minutes, $request)->getAttribute('password');
        Notification::send($user, new LoginCodeNotification(is_string($code) ? $code : '', $minutes, $locale));
    }

    /**
     * Checks a code and uses it up when it's right.
     *
     * @return string|null why it was refused (none, expired, origin, attempts, incorrect), or null when it's right
     */
    public function consume(Authenticatable $user, string $code, Request $request): ?string
    {
        return match ($this->consume->execute($user, preg_replace('/\D/', '', $code) ?? '', $request)) {
            ConsumeOneTimePasswordResult::Ok => null,
            ConsumeOneTimePasswordResult::NoOneTimePasswordsFound => 'none',
            ConsumeOneTimePasswordResult::OneTimePasswordExpired => 'expired',
            ConsumeOneTimePasswordResult::DifferentOrigin => 'origin',
            ConsumeOneTimePasswordResult::RateLimitExceeded => 'attempts',
            default => 'incorrect',
        };
    }

    public function minutes(): int
    {
        return max(1, min(60, (int) $this->settings->string('general.code_minutes', '10')));
    }
}
