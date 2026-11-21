<?php

declare(strict_types=1);

namespace Sunrice\Account\Listeners;

use Illuminate\Support\Facades\Notification;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Emails\EmailSettings;
use Sunrice\Account\Events\Registered;
use Sunrice\Account\Notifications\NewRegistrationNotification;
use Sunrice\Account\Support\Input;
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
        $notification = new NewRegistrationNotification(Input::string($event->data, 'name'), Input::string($event->data, 'email'), Locales::default());

        $staff = $this->settings->staff(Emails::STAFF_REGISTRATION);
        $known = [];
        foreach ($staff as $user) {
            $email = $user->getAttribute('email');
            $known[] = is_string($email) ? mb_strtolower($email) : '';
        }
        if ($staff->isNotEmpty()) {
            Notification::send($staff, $notification);
        }

        $addresses = array_values(array_diff($this->settings->addresses(Emails::STAFF_REGISTRATION), $known));
        if ($addresses !== []) {
            Notification::route('mail', $addresses)->notify($notification);
        }
    }
}
