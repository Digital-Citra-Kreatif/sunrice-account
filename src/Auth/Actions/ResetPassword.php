<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\Passwords;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Events\PasswordReset;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Support\Input;
use Sunrice\Admin\Auth\PasswordPolicy;
use Sunrice\Admin\Security\AccountGuard;

/**
 * Sets a new password with a reset link's token. The link proves the
 * email address, and a new remember token signs out "remember me" on
 * other devices.
 */
final class ResetPassword
{
    /**
     * @param  array<array-key, mixed>  $input  token, email, password, password_confirmation
     *
     * @throws ValidationException
     */
    public function handle(array $input): void
    {
        $data = Validator::make($input, [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255', 'confirmed', ...PasswordPolicy::rules()],
        ])->validate();

        $status = Passwords::broker()->reset(
            ['email' => Users::normalizeEmail(Input::string($data, 'email')), 'token' => Input::string($data, 'token'), 'password' => Input::string($data, 'password')],
            function (mixed $user, string $password): void {
                if (! $user instanceof Model || ! $user instanceof Authenticatable) {
                    return;
                }
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->getAttribute('email_verified_at') ?? now(),
                ])->save();
                Profile::forUser($user)->forceFill(['password_set' => true])->save();
                // Every session ends: one someone else holds, who may be why the password was reset.
                app(AccountGuard::class)->signOutEverywhere($user, false);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('account::auth.reset.invalid')]);
        }
    }
}
