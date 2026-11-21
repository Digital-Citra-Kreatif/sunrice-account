<?php

declare(strict_types=1);

namespace Sunrice\Account\Listeners;

use Illuminate\Support\Facades\Notification;
use Sunrice\Account\Emails\EmailSettings;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Events\Registered;
use Sunrice\Account\Notifications\NewRegistrationNotification;
use Sunrice\Cms\Support\Locales;

/**
 * Tells staff of a new account: the users with the email's roles, and
 * its own addresses, each once, in the site's main language.
 *
 * @internal
 */
final class NotifyStaffOfRegistration
{
    public function __construct(private EmailSettings $settings) {}

    public function handle(Registered $event): void
    {
        if (! $this->settings->enabled(Emails::STAFF_REGISTRATION)) {
            return;
        }
        $name = $event->data['name'] ?? '';
        $email = $event->data['email'] ?? '';
        $notification = new NewRegistrationNotification(is_string($name) ? $name : '', is_string($email) ? $email : '', Locales::default());

        $staff = $this->settings->staff(Emails::STAFF_REGISTRATION);
        if ($staff->isNotEmpty()) {
            Notification::send($staff, $notification);
        }
        $known = $staff->map(function ($user): string {
            $email = $user->getAttribute('email');

            return is_string($email) ? mb_strtolower($email) : '';
        })->all();
        $addresses = array_values(array_diff($this->settings->addresses(Emails::STAFF_REGISTRATION), $known));
        if ($addresses !== []) {
            Notification::route('mail', $addresses)->notify($notification);
        }
    }
}
