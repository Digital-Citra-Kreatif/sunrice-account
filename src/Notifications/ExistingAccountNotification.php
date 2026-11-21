<?php

declare(strict_types=1);

namespace Sunrice\Account\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Sunrice\Account\Emails\AccountEmail;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Routing\AccountRoutes;

/**
 * Someone tried to register with an email that has an account: its owner
 * is told, with the way in, instead of the form saying the email is taken.
 */
final class ExistingAccountNotification extends AccountEmail
{
    public static function key(): string
    {
        return Emails::EXISTING;
    }

    protected function build(MailMessage $mail, mixed $notifiable): MailMessage
    {
        return $mail
            ->line($this->text('intro'))
            ->action($this->text('action'), AccountRoutes::url('sunrice.account.login', [], $this->lang))
            ->line($this->text('forgot', ['url' => AccountRoutes::url('sunrice.account.password.request', [], $this->lang)]))
            ->line($this->text('ignore'));
    }
}
