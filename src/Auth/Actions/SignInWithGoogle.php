<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth\Actions;

use Illuminate\Auth\Events\Registered as LaravelRegistered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Two\User as OAuthUser;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Events\Registered;
use Sunrice\Account\Events\SignedIn;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Security\AccountGuard;

/**
 * Signs in the account a Google user is: the one already linked to it, or
 * the account of the same (Google-verified) email, which is then linked;
 * otherwise a new account, while registration is open. Google's word
 * verifies the email.
 */
final class SignInWithGoogle
{
    public const TABLE = 'account_social_accounts';

    public function __construct(
        private AccountSettings $settings,
        private AssignDefaultRoles $roles,
        private VerifyEmail $verify,
        private AccountGuard $accounts,
    ) {}

    /** @return Authenticatable|string the signed-in account, or why not (no_account, staff, locked) */
    public function handle(SocialUser $google, string $locale): Authenticatable|string
    {
        $id = (string) $google->getId();
        $email = Users::normalizeEmail((string) $google->getEmail());
        $verified = $google instanceof OAuthUser && ($google->getRaw()['email_verified'] ?? false) === true;

        $linked = DB::table(self::TABLE)->where('provider', 'google')->where('provider_id', $id)->value('user_id');
        $user = $linked !== null ? Users::model()::query()->whereKey($linked)->first() : null;
        if (! $user instanceof Authenticatable && $email !== '' && $verified) {
            $user = Users::findByEmail($email);
        }
        $created = false;
        if (! $user instanceof Authenticatable) {
            if (! $this->settings->registration || $email === '' || ! $verified) {
                return 'no_account';
            }
            $user = $this->create($google, $email, $locale);
            $created = true;
        }
        if (SiteGuard::refusesStaff($user)) {
            return 'staff';
        }
        if ($this->accounts->isLocked($user)) {
            return 'locked';
        }
        if ($linked === null) {
            DB::table(self::TABLE)->insert(['user_id' => $user->getAuthIdentifier(), 'provider' => 'google', 'provider_id' => $id,
                'email' => $email !== '' ? $email : null, 'avatar' => $google->getAvatar(), 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($verified) {
            $this->verify->handle($user, $locale);
        }
        if ($created) {
            event(new LaravelRegistered($user));
            event(new Registered($user, Profile::forUser($user), ['name' => $user->getAttribute('name'), 'email' => $email], $locale));
        }

        SiteGuard::get()->login($user, true);
        event(new SignedIn($user, 'google', true));

        return $user;
    }

    private function create(SocialUser $google, string $email, string $locale): Model&Authenticatable
    {
        return DB::transaction(function () use ($google, $email, $locale): Model&Authenticatable {
            $model = Users::model();
            $user = new $model;
            $name = trim((string) $google->getName());
            $user->forceFill([
                'name' => $name !== '' ? $name : Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ])->save();
            $this->roles->handle($user);
            Profile::forUser($user)->forceFill(['locale' => $locale, 'password_set' => false])->save();

            return $user;
        });
    }
}
