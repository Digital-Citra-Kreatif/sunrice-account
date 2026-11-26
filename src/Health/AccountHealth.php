<?php

declare(strict_types=1);

namespace Sunrice\Account\Health;

use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Account\Auth\Google;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Panel;
use Throwable;

/**
 * Whether the app has what accounts need: the user model's traits, the
 * tables, the roles new accounts get, what the switched-on sign-in
 * methods rely on, and mail. `php artisan account:check` prints it, and
 * `sunrice:install` and `account:install` end with it.
 *
 * Each check only looks (the schema, config, a few rows): nothing is
 * changed and nothing is sent.
 *
 * @api
 */
final class AccountHealth
{
    public function __construct(private AccountSettings $settings, private Panel $panel) {}

    /** @return list<Check> */
    public function checks(): array
    {
        return [
            $this->userModel(),
            $this->tables(),
            $this->roles(),
            $this->loginCodes(),
            $this->google(),
            $this->mail(),
            $this->guard(),
        ];
    }

    /** @return list<Check> those that aren't fine */
    public function problems(): array
    {
        return array_values(array_filter($this->checks(), fn (Check $check): bool => ! $check->passed()));
    }

    /** Whether nothing is broken (warnings aside). */
    public function healthy(): bool
    {
        return array_filter($this->checks(), fn (Check $check): bool => $check->status === Check::ERROR) === [];
    }

    private function userModel(): Check
    {
        $model = Users::model();
        $traits = class_uses_recursive($model);
        $missing = array_keys(array_filter([
            HasRoles::class => ! in_array(HasRoles::class, $traits, true),
            Notifiable::class => ! in_array(Notifiable::class, $traits, true),
        ]));

        return $missing === []
            ? Check::ok('user-model', 'User model', $model)
            : Check::error('user-model', 'User model', "{$model} needs ".implode(' and ', array_map(fn (string $trait): string => "the {$trait} trait", $missing)).': accounts get roles and emails through them.');
    }

    private function tables(): Check
    {
        try {
            $users = (new (Users::model()))->getTable();
            $columns = array_values(array_filter(['name', 'email', 'password', 'email_verified_at', 'remember_token'], fn (string $column): bool => ! Schema::hasColumn($users, $column)));
            if ($columns !== []) {
                return Check::error('tables', 'Tables', "The {$users} table needs the column".(count($columns) === 1 ? '' : 's').' '.implode(', ', $columns).' (as Laravel\'s own users migration has).');
            }
            if (! Schema::hasTable('account_profiles') || ! Schema::hasColumn('account_profiles', 'anonymized_at') || ! Schema::hasTable('account_social_accounts')) {
                return Check::error('tables', 'Tables', 'The account tables aren\'t up to date: run php artisan migrate.');
            }
        } catch (Throwable $e) {
            return Check::error('tables', 'Tables', 'The database couldn\'t be read: '.$e->getMessage());
        }

        return Check::ok('tables', 'Tables');
    }

    private function roles(): Check
    {
        $names = $this->settings->roles();
        if ($names === []) {
            return Check::ok('roles', 'Roles of new accounts', 'None');
        }
        $guard = $this->panel->getGuard();
        $existing = [];
        $privileged = [];
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', $names)->withCount('permissions')->get() as $role) {
            $existing[] = $role->name;
            if ($role->getAttribute('permissions_count') > 0) {
                $privileged[] = $role->name;
            }
        }
        $missing = array_values(array_diff($names, $existing));

        return match (true) {
            $privileged !== [] => Check::error('roles', 'Roles of new accounts', 'New accounts get '.implode(', ', $privileged).', which '.(count($privileged) === 1 ? 'has' : 'have').' permissions: choose roles without any in Settings → Accounts.'),
            $missing !== [] => Check::warning('roles', 'Roles of new accounts', implode(', ', $missing).' '.(count($missing) === 1 ? 'doesn\'t' : 'don\'t').' exist yet (created on the first registration): run php artisan sunrice:roles.'),
            default => Check::ok('roles', 'Roles of new accounts', implode(', ', $names)),
        };
    }

    private function loginCodes(): Check
    {
        if (! $this->settings->loginCode) {
            return Check::ok('login-codes', 'Sign-in codes', 'Off');
        }
        $model = Users::model();
        if (! in_array(HasOneTimePasswords::class, class_uses_recursive($model), true)) {
            return Check::error('login-codes', 'Sign-in codes', "On, but {$model} doesn't use the ".HasOneTimePasswords::class.' trait: add it, or switch codes off in Settings → Accounts.');
        }
        if (! Schema::hasTable('one_time_passwords')) {
            return Check::error('login-codes', 'Sign-in codes', 'On, but the one_time_passwords table is missing: publish spatie/laravel-one-time-passwords\' migration and run php artisan migrate.');
        }

        return Check::ok('login-codes', 'Sign-in codes', 'On');
    }

    private function google(): Check
    {
        if (! $this->settings->googleLogin) {
            return Check::ok('google', 'Sign in with Google', 'Off');
        }

        return Google::configured()
            ? Check::ok('google', 'Sign in with Google', 'On')
            : Check::warning('google', 'Sign in with Google', 'On, but without credentials, so the button doesn\'t show: set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET (config/services.php, "google").');
    }

    private function mail(): Check
    {
        $mailer = config('mail.default');
        $mailer = is_string($mailer) ? $mailer : '';
        $transport = config("mail.mailers.{$mailer}.transport");
        if (in_array($transport, ['log', 'array', null], true)) {
            $why = $transport === null ? "the mailer \"{$mailer}\" isn't set up" : "the mailer \"{$mailer}\" only writes them to the {$transport}";

            return Check::warning('mail', 'Email', "Emails aren't delivered ({$why}): nobody gets a verification link, a password link or a sign-in code. Set MAIL_MAILER.");
        }
        $queue = config('queue.default');

        return Check::ok('mail', 'Email', $queue === 'sync' ? $mailer : "{$mailer}, queued on \"".(is_string($queue) ? $queue : '').'": keep a queue worker running');
    }

    private function guard(): Check
    {
        return SiteGuard::sharesPanelGuard()
            ? Check::warning('guard', 'Guard', 'The site shares the admin\'s guard ('.SiteGuard::name().'): signing out of the site signs out of the admin, and staff can\'t sign in on the site. Set sunrice-account.auth.guard to a guard of its own unless that is what you want.')
            : Check::ok('guard', 'Guard', SiteGuard::name());
    }
}
