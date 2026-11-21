<?php

declare(strict_types=1);

namespace Sunrice\Account\Notifications;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use LogicException;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Emails\AccountEmail;
use Sunrice\Account\Emails\Emails;

/** The link that verifies an account's email address, in the language it was sent in. */
final class VerifyEmailNotification extends AccountEmail
{
    public static function key(): string
    {
        return Emails::VERIFY;
    }

    protected function build(MailMessage $mail, mixed $notifiable): MailMessage
    {
        if (! $notifiable instanceof Model || ! $notifiable instanceof Authenticatable) {
            throw new LogicException('Only users verify their email.');
        }

        return $mail
            ->line($this->text('intro'))
            ->action($this->text('action'), Verification::url($notifiable, $this->lang))
            ->line($this->text('ignore'));
    }
}
