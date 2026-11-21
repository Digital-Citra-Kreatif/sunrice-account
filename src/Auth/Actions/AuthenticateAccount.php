<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Events\SignedIn;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Security\AccountGuard;

/**
 * Signs an account in on the site with its email and password. Every
 * refusal reads the same ("These details don't match"), except for staff
 * while the site shares the admin's guard (they're told to use the
 * admin's sign-in, which has its own protections), and, once the password
 * is right, an unverified email while verification is required (a new
 * link is sent, at most once a minute).
 */
final class AuthenticateAccount
{
    public function __construct(
        private AccountGuard $accounts,
        private AccountSettings $settings,
        private SendVerificationLink $verification,
    ) {}

    /** @throws ValidationException */
    public function handle(string $email, string $password, bool $remember = false): Authenticatable
    {
        $user = Users::findByEmail($email);
        if ($user === null || ! self::passwordMatches($user, $password) || $this->accounts->isLocked($user)) {
            throw ValidationException::withMessages(['email' => __('account::auth.login.failed')]);
        }
        if (SiteGuard::refusesStaff($user)) {
            throw ValidationException::withMessages(['email' => __('account::auth.login.staff')]);
        }
        if ($this->settings->verifyEmail && ! Verification::verified($user)) {
            $this->verification->handle($user, app()->getLocale());

            throw ValidationException::withMessages(['email' => __('account::auth.verify.required')]);
        }

        SiteGuard::get()->login($user, $remember);
        event(new SignedIn($user, 'password', $remember));

        return $user;
    }

    private static function passwordMatches(Authenticatable $user, string $password): bool
    {
        $hash = $user->getAuthPassword();

        return $hash !== '' && Hash::check($password, $hash);
    }
}
