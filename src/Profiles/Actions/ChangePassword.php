<?php

declare(strict_types=1);

namespace Sunrice\Account\Profiles\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\Throttle;
use Sunrice\Account\Events\PasswordChanged;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Support\FieldNames;
use Sunrice\Account\Support\Input;
use Sunrice\Admin\Auth\PasswordPolicy;
use Sunrice\Admin\Security\AccountGuard;

/**
 * Changes an account's password from its profile page: the current one
 * first, unless it never set one (accounts made by Google). A new remember
 * token signs out "remember me" on other devices.
 */
final class ChangePassword
{
    /**
     * @param  array<array-key, mixed>  $input  current_password, password, password_confirmation
     *
     * @throws ValidationException
     */
    public function handle(Model&Authenticatable $user, array $input): void
    {
        $profile = Profile::forUser($user);
        $data = Validator::make($input, [
            'current_password' => [$profile->password_set ? 'required' : 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', ...PasswordPolicy::rules($user)],
        ], [], FieldNames::all(newPassword: true))->validate();
        Throttle::account('password', $user->getKey(), 'current_password');
        if ($profile->password_set && ! Hash::check(Input::string($data, 'current_password'), $user->getAuthPassword())) {
            Throttle::account('password', $user->getKey(), 'current_password', hit: true);

            throw ValidationException::withMessages(['current_password' => __('account::account.password.current_wrong')]);
        }

        $user->forceFill(['password' => Hash::make(Input::string($data, 'password')), 'remember_token' => Str::random(60)])->save();
        $profile->forceFill(['password_set' => true])->save();
        // Every other session ends (one someone else holds too); this one stays.
        app(AccountGuard::class)->signOutEverywhere($user, false);
        if (request()->hasSession()) {
            request()->session()->put(AccountGuard::LOGIN_AT, now()->getTimestamp());
        }
        event(new PasswordChanged($user));
    }
}
