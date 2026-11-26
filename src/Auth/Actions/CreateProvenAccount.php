<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Auth\Events\Registered as LaravelRegistered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Events\Registered;
use Sunrice\Account\Events\Registering;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Support\Input;

/**
 * Creates the account of an address that was just proven (an emailed
 * code typed back, Google vouching for it): no form, no password, no link
 * to follow. The account is verified at once and has no password until
 * its owner sets one on the profile page; its name is what the provider
 * gave, else the part of the address before the @, theirs to change.
 *
 * The registration form's own fields (phone, terms, plugins' fields)
 * aren't asked: whatever needs them asks where it needs them.
 */
final class CreateProvenAccount
{
    public function __construct(private AssignDefaultRoles $roles, private VerifyEmail $verify) {}

    /**
     * @throws ValidationException when a listener of `Registering` refuses the account
     */
    public function handle(string $email, ?string $name, string $locale): Model&Authenticatable
    {
        $email = Users::normalizeEmail($email);
        $name = trim((string) $name);
        $event = new Registering([
            'name' => $name !== '' ? $name : Str::before($email, '@'),
            'email' => $email,
            'phone' => null,
            'accepts_marketing' => false,
            'fields' => [],
        ], $locale);
        event($event);
        $values = $event->data;

        [$user, $profile] = DB::transaction(function () use ($values, $email, $locale): array {
            $model = Users::model();
            $user = new $model;
            $user->forceFill([
                'name' => Input::string($values, 'name'),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ])->save();
            $this->roles->handle($user);
            $profile = Profile::forUser($user);
            $profile->forceFill(['phone' => Input::nullableString($values, 'phone'), 'locale' => $locale, 'password_set' => false])->save();

            return [$user, $profile];
        });

        // Verified before it is announced: the welcome email goes out once, for the verification.
        $this->verify->handle($user, $locale);
        event(new LaravelRegistered($user));
        event(new Registered($user, $profile, $values, $locale));

        return $user;
    }
}
