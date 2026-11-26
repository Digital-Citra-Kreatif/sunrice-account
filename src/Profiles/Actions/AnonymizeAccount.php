<?php

declare(strict_types=1);

namespace Sunrice\Account\Profiles\Actions;

use DomainException;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Sunrice\Account\Admin\Accounts;
use Sunrice\Account\Auth\Actions\SignInWithGoogle;
use Sunrice\Account\Auth\Passwords;
use Sunrice\Account\Events\Anonymized;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityLog;

/**
 * Removes an account's personal data, for a deletion request or on staff's
 * own decision. The user row stays, since orders, comments and logs point
 * at it, but nothing in it names the person any more: the name and email
 * become placeholders, the password a random one, and the phone, language,
 * marketing consent, plugins' profile fields, roles, Google links, sign-in
 * codes, reset links and sessions go. It can't be undone.
 *
 * Plugins clear their own records in a listener of `Anonymized`.
 */
final class AnonymizeAccount
{
    /** The name an anonymised account shows under. */
    public const NAME = 'Deleted account';

    /** The domain of an anonymised account's placeholder address (never deliverable). */
    public const DOMAIN = 'invalid.local';

    public function __construct(private AccountGuard $accounts, private SecurityLog $log) {}

    /**
     * @return bool whether it was anonymised just now (false when it already was)
     *
     * @throws DomainException for someone who opens the admin: staff are managed in Users
     */
    public function handle(Model&Authenticatable $user): bool
    {
        if (Accounts::isStaff($user)) {
            throw new DomainException('Staff are managed in Users.');
        }
        $profile = Profile::forUser($user);
        if ($profile->anonymized_at !== null) {
            return false;
        }
        $email = is_string($user->getAttribute('email')) ? $user->getAttribute('email') : '';
        $key = $user->getKey();
        $id = is_scalar($key) ? (string) $key : '';

        DB::transaction(function () use ($user, $profile, $email, $id): void {
            // Before the address changes: the broker finds the token by it.
            $broker = Passwords::broker();
            if ($broker instanceof PasswordBroker && $user instanceof CanResetPassword) {
                $broker->deleteToken($user);
            }

            $user->forceFill([
                'name' => self::NAME,
                'email' => 'deleted-'.$id.'-'.Str::lower(Str::random(8)).'@'.self::DOMAIN,
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
            ])->save();
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([]);
            }
            if (method_exists($user, 'oneTimePasswords')) {
                $user->oneTimePasswords()->delete();
            }
            DB::table(SignInWithGoogle::TABLE)->where('user_id', $user->getKey())->delete();

            $profile->forceFill([
                'phone' => null,
                'locale' => null,
                'accepts_marketing' => false,
                'marketing_consent_at' => null,
                'password_set' => false,
                'meta' => null,
                'anonymized_at' => now(),
            ])->save();

            event(new Anonymized($user, $profile, $email));
        });

        $this->accounts->signOutEverywhere($user, false);
        // Without the address it had: the log would keep what was just removed.
        $this->log->record('personal data removed', $user, self::NAME.' #'.$id);

        return true;
    }
}
