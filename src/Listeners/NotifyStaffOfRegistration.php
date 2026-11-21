<?php

declare(strict_types=1);

namespace Sunrice\Account\Listeners;

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
        $notification = new NewRegistrationNotification(Input::string($event->data, 'name'), Input::string($event->data, 'email'), Locales::default());

        $this->settings->notifyStaff(Emails::STAFF_REGISTRATION, $notification);
    }
}
