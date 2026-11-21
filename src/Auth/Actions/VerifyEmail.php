<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Auth\Events\Verified as LaravelVerified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Events\Verified;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Admin\Security\AccountGuard;

/** Marks an account's email verified (from its link, an emailed code or Google). */
final class VerifyEmail
{
    public function __construct(private AccountGuard $accounts) {}

    /**
     * Verifies an email proven by someone signing in with it (an emailed
     * code, Google) rather than by the verification link. An unverified
     * account may have been made by someone else with that address and a
     * password of their choosing, before its owner came: its password and
     * every other session end first, so they can't keep a way in.
     *
     * @return bool whether it was verified just now
     */
    public function claim(Model&Authenticatable $user, string $locale): bool
    {
        if (Verification::verified($user)) {
            return false;
        }
        $user->forceFill(['password' => Hash::make(Str::random(64)), 'remember_token' => Str::random(60)])->save();
        Profile::forUser($user)->forceFill(['password_set' => false])->save();
        $this->accounts->signOutEverywhere($user, false);

        return $this->handle($user, $locale);
    }

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
