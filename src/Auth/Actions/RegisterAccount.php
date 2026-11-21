<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Auth\Events\Registered as LaravelRegistered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Events\Registered;
use Sunrice\Account\Events\Registering;
use Sunrice\Account\Notifications\ExistingAccountNotification;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Account\Support\Input;
use Sunrice\Admin\Auth\PasswordPolicy;

/**
 * Creates an account from the registration form: the user (in the app's
 * users table), its default roles and its profile. An email that already
 * has an account creates nothing: its owner is told by email instead, and
 * the visitor gets the same answer, so the form never reveals who has an
 * account.
 */
final class RegisterAccount
{
    public function __construct(private AccountSettings $settings, private AssignDefaultRoles $roles) {}

    /**
     * @param  array<string, mixed>  $input
     * @return Authenticatable|null the new account, or null when the email already had one
     */
    public function handle(array $input, string $locale): ?Authenticatable
    {
        $data = Validator::make($input, $this->rules(), [], [
            'terms' => __('account::auth.register.terms_name'),
        ])->validate();

        $event = new Registering([
            'name' => Input::string($data, 'name'),
            'email' => Users::normalizeEmail(Input::string($data, 'email')),
            'phone' => Input::nullableString($data, 'phone'),
            'accepts_marketing' => (bool) ($data['accepts_marketing'] ?? false),
        ], $locale);
        event($event);
        $values = $event->data;
        $email = Users::normalizeEmail(Input::string($values, 'email'));

        $existing = Users::findByEmail($email);
        if ($existing !== null) {
            Notification::send($existing, new ExistingAccountNotification($locale));

            return null;
        }

        [$user, $profile] = DB::transaction(function () use ($values, $email, $data, $locale): array {
            $model = Users::model();
            $user = new $model;
            $user->forceFill([
                'name' => Input::string($values, 'name'),
                'email' => $email,
                'password' => Hash::make(Input::string($data, 'password')),
            ])->save();
            $this->roles->handle($user);
            $consent = (bool) ($values['accepts_marketing'] ?? false);
            $profile = Profile::forUser($user);
            $profile->forceFill([
                'phone' => Input::nullableString($values, 'phone'),
                'locale' => $locale,
                'accepts_marketing' => $consent,
                'marketing_consent_at' => $consent ? now() : null,
            ])->save();

            return [$user, $profile];
        });

        event(new LaravelRegistered($user));
        event(new Registered($user, $profile, $values, $locale));

        return $user;
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        $phone = $this->settings->string('general.phone', 'optional');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'phone' => $phone === 'required' ? ['required', 'string', 'max:30'] : ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255', 'confirmed', ...PasswordPolicy::rules()],
            'accepts_marketing' => ['nullable', 'boolean'],
            'terms' => $this->settings->string('general.terms_url') !== '' ? ['accepted'] : ['nullable'],
        ];
    }
}
