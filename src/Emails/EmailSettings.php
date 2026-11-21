<?php

declare(strict_types=1);

namespace Sunrice\Account\Emails;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\ListInput;

/**
 * An email's settings: for the package's emails, Settings → Accounts →
 * Emails (AccountSettings::EMAILS); for a plugin's, what its
 * definition's `settings` closure gives.
 *
 * @api
 */
final class EmailSettings
{
    public function __construct(private Emails $emails) {}

    /**
     * @return array{enabled?: bool, subject?: string, addresses?: list<string>, roles?: list<string>|null}
     */
    public function for(string $key): array
    {
        $email = $this->emails->get($key);
        $prefix = AccountSettings::EMAILS[$key] ?? null;
        if ($prefix !== null) {
            $values = app(AccountSettings::class)->toArray();
            $settings = [];
            if (is_bool($values[$prefix.'Email'] ?? null)) {
                $settings['enabled'] = $values[$prefix.'Email'];
            }
            if (is_string($values[$prefix.'Subject'] ?? null)) {
                $settings['subject'] = $values[$prefix.'Subject'];
            }
            if (is_string($values[$prefix.'Addresses'] ?? null)) {
                $settings['addresses'] = ListInput::items($values[$prefix.'Addresses'], emails: true);
            }
            if (array_key_exists($prefix.'Roles', $values)) {
                $roles = $values[$prefix.'Roles'];
                $settings['roles'] = is_array($roles) ? self::strings($roles) : null;
            }

            return $settings;
        }

        if ($email === null || $email->settings === null) {
            return [];
        }

        return ($email->settings)();
    }

    /** Whether it's sent: never when unknown, always when locked, else its switch. */
    public function enabled(string $key): bool
    {
        $email = $this->emails->get($key);
        if ($email === null) {
            return false;
        }
        if ($email->locked) {
            return true;
        }

        return $this->for($key)['enabled'] ?? $email->enabledByDefault;
    }

    /** The subject set for a language, or null for the default text. */
    public function subject(string $key, string $locale): ?string
    {
        $subject = AccountSettings::line($this->for($key)['subject'] ?? '', $locale);

        return $subject !== '' ? $subject : null;
    }

    /** @return list<string> a staff email's own addresses (valid, lower case) */
    public function addresses(string $key): array
    {
        $addresses = [];
        foreach ($this->for($key)['addresses'] ?? [] as $address) {
            $address = Users::normalizeEmail($address);
            if (filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    /** @return list<string> the roles whose users get a staff email (default: super admins and administrators) */
    public function roles(string $key): array
    {
        return $this->for($key)['roles'] ?? AccountSettings::defaultStaffRoles();
    }

    /**
     * Sends a staff email, when it's on, to the users with its roles and to
     * its addresses, each once.
     */
    public function notifyStaff(string $key, Notification $notification): void
    {
        if (! $this->enabled($key)) {
            return;
        }
        $staff = $this->staff($key);
        $known = [];
        foreach ($staff as $user) {
            $email = $user->getAttribute('email');
            $known[] = is_string($email) ? mb_strtolower($email) : '';
        }
        if ($staff->isNotEmpty()) {
            NotificationFacade::send($staff, $notification);
        }
        $addresses = array_values(array_diff($this->addresses($key), $known));
        if ($addresses !== []) {
            NotificationFacade::route('mail', $addresses)->notify($notification);
        }
    }

    /** @return Collection<int, Model&Authenticatable> the users with a staff email's roles */
    public function staff(string $key): Collection
    {
        $roles = $this->roles($key);
        if ($roles === []) {
            return new Collection;
        }

        // The panel guard's roles only, and at most 100 people: a staff email never goes to every customer.
        return Users::model()::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles)->where('guard_name', app(Panel::class)->getGuard()))
            ->limit(100)->get();
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private static function strings(array $values): array
    {
        $strings = [];
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $strings[] = trim($value);
            }
        }

        return $strings;
    }
}
