<?php

declare(strict_types=1);

namespace Sunrice\Account\Listeners;

use Illuminate\Support\Facades\Notification;
use Sunrice\Account\Events\Registered;
use Sunrice\Account\Events\Verified;
use Sunrice\Account\Notifications\WelcomeNotification;
use Sunrice\Account\Settings\AccountSettings;

/**
 * The welcome email, once a new account is ready: at registration while
 * emails aren't verified, else when its email is verified.
 *
 * @internal
 */
final class SendWelcomeEmail
{
    public function __construct(private AccountSettings $settings) {}

    public function handle(Registered|Verified $event): void
    {
        if ($event instanceof Registered && $this->settings->boolean('general.verify_email', true)) {
            return;
        }
        Notification::send($event->user, new WelcomeNotification($event->locale));
    }
}
