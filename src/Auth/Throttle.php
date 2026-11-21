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

    /** `$ipDecaySeconds`: how long the IP's count lasts, when not as long as the email's. */
    public static function hit(string $name, Request $request, string $email, int $decaySeconds = 60, ?int $ipDecaySeconds = null): void
    {
        [$perEmail, $perIp] = self::keys($name, $request, $email);
        RateLimiter::hit($perEmail, $decaySeconds);
        RateLimiter::hit($perIp, $ipDecaySeconds ?? $decaySeconds);
    }

    /**
     * A limit on one signed-in account's attempts (its current password on
     * the profile page): `$max` failures per `$decaySeconds`, from any IP.
     *
     * @throws ValidationException when there were too many
     */
    public static function account(string $name, mixed $key, string $field, int $max = 5, int $decaySeconds = 600, bool $hit = false): void
    {
        $limiter = "account-{$name}:user:".(is_scalar($key) ? (string) $key : '');
        if ($hit) {
            RateLimiter::hit($limiter, $decaySeconds);

            return;
        }
        if (RateLimiter::tooManyAttempts($limiter, $max)) {
            throw ValidationException::withMessages([$field => __('account::auth.throttled', ['seconds' => RateLimiter::availableIn($limiter)])]);
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
