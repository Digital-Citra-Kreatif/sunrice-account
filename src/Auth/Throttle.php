<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rate limits for the sign-in forms, keyed by email + IP and by IP alone
 * (like the panel's own sign-in).
 *
 * @internal
 */
final class Throttle
{
    /** @throws ValidationException when there were too many attempts */
    public static function check(string $name, Request $request, string $email, int $perEmail, int $perIp, string $field = 'email'): void
    {
        foreach (self::keys($name, $request, $email) as $i => $key) {
            if (RateLimiter::tooManyAttempts($key, $i === 0 ? $perEmail : $perIp)) {
                throw ValidationException::withMessages([$field => __('account::auth.throttled', ['seconds' => RateLimiter::availableIn($key)])]);
            }
        }
    }

    public static function hit(string $name, Request $request, string $email, int $decaySeconds = 60): void
    {
        foreach (self::keys($name, $request, $email) as $key) {
            RateLimiter::hit($key, $decaySeconds);
        }
    }

    /** Forget the email + IP attempts (after a success); the IP's stay. */
    public static function clear(string $name, Request $request, string $email): void
    {
        RateLimiter::clear(self::keys($name, $request, $email)[0]);
    }

    /** @return array{0: string, 1: string} */
    private static function keys(string $name, Request $request, string $email): array
    {
        $ip = (string) $request->ip();

        return ["account-{$name}:".Str::transliterate(Users::normalizeEmail($email)).'|'.$ip, "account-{$name}:ip:".$ip];
    }
}
