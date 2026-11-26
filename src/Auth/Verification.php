<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/**
 * Email verification: an account's email is verified when the user's
 * `email_verified_at` is set (by its verification link, a password reset,
 * or staff). The link is signed, works for `auth.verification.expire`
 * minutes (a day by default here), and carries the language it was sent in.
 *
 * @api
 */
final class Verification
{
    public static function verified(Authenticatable $user): bool
    {
        return $user instanceof Model && $user->getAttribute('email_verified_at') !== null;
    }

    /** The signed verification link of a user's current email, in a language, leading on to `$return` (an address of this site) once it has signed them in. */
    public static function url(Model&Authenticatable $user, string $locale, ?string $return = null): string
    {
        $minutes = config('auth.verification.expire', 1440);

        return URL::temporarySignedRoute('sunrice.account.verify', now()->addMinutes(is_numeric($minutes) ? (int) $minutes : 1440), [
            'id' => $user->getAuthIdentifier(),
            'hash' => self::hash($user),
            'lang' => $locale,
            // Where they were going when they registered: the link may be opened in another browser, without that session.
            ...($return !== null && $return !== '' ? ['return' => $return] : []),
        ]);
    }

    /** What the link carries to prove it was sent to the current email (a changed email invalidates it). */
    public static function hash(Model $user): string
    {
        $email = $user->getAttribute('email');

        return sha1(is_string($email) ? Users::normalizeEmail($email) : '');
    }
}
