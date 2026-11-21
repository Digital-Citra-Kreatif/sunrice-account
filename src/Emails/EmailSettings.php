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

/**
 * An email's settings, kept in the account settings under
 * `emails.{key}`: `enabled`, `subject` (per language), and for staff
 * emails the `addresses` and `roles` it goes to.
 *
 * @api
 */
final class EmailSettings
{
    public function __construct(private AccountSettings $settings, private Emails $emails) {}

    /** @return array<array-key, mixed> */
    public function for(string $key): array
    {
        $values = $this->settings->section('emails')[$key] ?? [];

        return is_array($values) ? $values : [];
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
        $enabled = $this->for($key)['enabled'] ?? null;

        return is_bool($enabled) ? $enabled : $email->enabledByDefault;
    }

    /** The subject set for a language, or null for the default text. */
    public function subject(string $key, string $locale): ?string
    {
        $subjects = $this->for($key)['subject'] ?? [];
        $subject = is_array($subjects) ? ($subjects[$locale] ?? null) : null;

        return is_string($subject) && trim($subject) !== '' ? trim($subject) : null;
    }

    /** @return list<string> a staff email's own addresses (valid, lower case) */
    public function addresses(string $key): array
    {
        $stored = $this->for($key)['addresses'] ?? [];
        $list = is_string($stored) ? (preg_split('/[\s,;]+/', $stored) ?: []) : (is_array($stored) ? $stored : []);
        $addresses = [];
        foreach ($list as $address) {
            $address = is_string($address) ? Users::normalizeEmail($address) : '';
            if (filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    /** @return list<string> the roles whose users get a staff email (default: super admins and administrators) */
    public function roles(string $key): array
    {
        $stored = $this->for($key)['roles'] ?? null;
        if (! is_array($stored)) {
            $superAdmin = config('sunrice-admin.super_admin_role', 'super-admin');

            return [is_string($superAdmin) ? $superAdmin : 'super-admin', 'administrator'];
        }

        return array_values(array_filter($stored, fn (mixed $role): bool => is_string($role) && $role !== ''));
    }

    /**
     * Sends a staff email (while it's on) to the users with its roles and to
     * its own addresses, each once.
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

        return Users::model()::query()->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))->get();
    }
}
