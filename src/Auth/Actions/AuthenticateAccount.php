<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Events\SignedIn;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;

/**
 * Signs an account in on the site with its email and password. Every
 * refusal reads the same ("These details don't match"), except for staff
 * while the site shares the admin's guard: they're told to use the
 * admin's sign-in, which has its own protections (two-factor, lockouts).
 */
final class AuthenticateAccount
{
    public function __construct(private AccountGuard $accounts) {}

    /** @throws ValidationException */
    public function handle(string $email, string $password, bool $remember = false): Authenticatable
    {
        $user = Users::findByEmail($email);
        if ($user === null || ! self::passwordMatches($user, $password) || $this->accounts->isLocked($user)) {
            throw ValidationException::withMessages(['email' => __('account::auth.login.failed')]);
        }
        if (SiteGuard::sharesPanelGuard() && $user instanceof Authorizable && $user->can(Panel::ACCESS)) {
            throw ValidationException::withMessages(['email' => __('account::auth.login.staff')]);
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
