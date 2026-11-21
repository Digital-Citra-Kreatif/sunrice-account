<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Auth\Events\Verified as LaravelVerified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Events\Verified;

/** Marks an account's email verified (from its link). */
final class VerifyEmail
{
    /** @return bool whether it was verified just now (false when it already was) */
    public function handle(Model&Authenticatable $user, string $locale): bool
    {
        if (Verification::verified($user)) {
            return false;
        }
        $user->forceFill(['email_verified_at' => now()])->save();
        if ($user instanceof MustVerifyEmail) {
            event(new LaravelVerified($user));
        }
        event(new Verified($user, $locale));

        return true;
    }
}
