<?php

declare(strict_types=1);

namespace Sunrice\Account\Profiles\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\Actions\SendVerificationLink;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Events\ProfileUpdated;
use Sunrice\Account\Events\ProfileUpdating;
use Sunrice\Account\MyAccount\Fields;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Account\Support\Input;

/**
 * Saves the profile page: name, email, phone, marketing consent and the
 * profile fields plugins add. A new email needs the current password
 * (for accounts that have one: a stolen session can't move the account to
 * another address) and, while emails are verified, is verified again.
 */
final class UpdateProfile
{
    public function __construct(private AccountSettings $settings, private Fields $fields, private SendVerificationLink $verification) {}

    /**
     * @param  array<array-key, mixed>  $input
     * @return bool whether a verification link went to a new email
     *
     * @throws ValidationException
     */
    public function handle(Model&Authenticatable $user, array $input, string $locale): bool
    {
        $profile = Profile::forUser($user);
        $fields = $this->fields->profile();
        $phone = $this->settings->string('general.phone', 'optional');
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique($user->getTable(), 'email')->ignore($user->getKey(), $user->getKeyName())],
            'phone' => $phone === 'required' ? ['required', 'string', 'max:30'] : ['nullable', 'string', 'max:30'],
            'accepts_marketing' => ['nullable', 'boolean'],
            'current_password' => ['nullable', 'string', 'max:255'],
            ...Fields::rules($fields),
        ])->validate();

        $event = new ProfileUpdating($user, [
            'name' => Input::string($data, 'name'),
            'email' => Users::normalizeEmail(Input::string($data, 'email')),
            'phone' => $phone === 'off' ? $profile->phone : Input::nullableString($data, 'phone'),
            'accepts_marketing' => $this->settings->boolean('general.marketing_consent', true) ? (bool) ($data['accepts_marketing'] ?? false) : $profile->accepts_marketing,
            'fields' => Fields::values($fields, $data),
        ]);
        event($event);
        $values = $event->data;

        $name = Input::string($values, 'name');
        $email = Users::normalizeEmail(Input::string($values, 'email'));
        $current = $user->getAttribute('email');
        $emailChanged = $email !== Users::normalizeEmail(is_string($current) ? $current : '');
        if ($emailChanged && $profile->password_set && ! Hash::check(Input::string($data, 'current_password'), $user->getAuthPassword())) {
            throw ValidationException::withMessages(['current_password' => __('account::account.profile.email_needs_password')]);
        }
        $verify = $emailChanged && $this->settings->boolean('general.verify_email', true);
        $phoneValue = Input::nullableString($values, 'phone');
        $consent = (bool) ($values['accepts_marketing'] ?? false);
        $extra = [];
        foreach (is_array($values['fields'] ?? null) ? $values['fields'] : [] as $key => $value) {
            $extra[(string) $key] = $value;
        }

        $meta = $profile->meta ?? [];
        $changed = array_keys(array_filter([
            'name' => $user->getAttribute('name') !== $name,
            'email' => $emailChanged,
            'phone' => $profile->phone !== $phoneValue,
            'accepts_marketing' => $profile->accepts_marketing !== $consent,
        ]));
        foreach ($extra as $key => $value) {
            if (($meta[$key] ?? null) !== $value) {
                $changed[] = 'fields.'.$key;
            }
        }

        DB::transaction(function () use ($user, $profile, $name, $email, $verify, $phoneValue, $consent, $extra): void {
            $user->forceFill(['name' => $name, 'email' => $email, ...($verify ? ['email_verified_at' => null] : [])])->save();
            $profile->forceFill([
                'phone' => $phoneValue,
                'accepts_marketing' => $consent,
                'marketing_consent_at' => $consent ? ($profile->marketing_consent_at ?? now()) : null,
            ])->save();
            Fields::store($profile, $extra);
        });

        event(new ProfileUpdated($user, $profile, $changed));
        if ($verify) {
            $this->verification->handle($user, $locale);
        }

        return $verify;
    }
}
