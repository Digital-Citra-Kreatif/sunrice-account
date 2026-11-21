<?php

declare(strict_types=1);

namespace Sunrice\Account\Listeners;

use Illuminate\Database\Eloquent\Model;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Emails\EmailSettings;
use Sunrice\Account\Events\DeletionRequested;
use Sunrice\Account\Notifications\DeletionRequestNotification;
use Sunrice\Cms\Support\Locales;

/** @internal Tells staff an account asked to be deleted. */
final class NotifyStaffOfDeletionRequest
{
    public function __construct(private EmailSettings $settings) {}

    public function handle(DeletionRequested $event): void
    {
        $name = $event->user instanceof Model ? $event->user->getAttribute('name') : null;
        $email = $event->user instanceof Model ? $event->user->getAttribute('email') : null;
        $this->settings->notifyStaff(Emails::STAFF_DELETION, new DeletionRequestNotification(is_string($name) ? $name : '', is_string($email) ? $email : '', Locales::default()));
    }
}
