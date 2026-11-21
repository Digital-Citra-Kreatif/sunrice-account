<?php

declare(strict_types=1);

namespace Sunrice\Account\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Sunrice\Account\Emails\AccountEmail;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Routing\AccountRoutes;

/** A new account is ready: a welcome with the way to My Account. */
final class WelcomeNotification extends AccountEmail
{
    public static function key(): string
    {
        return Emails::WELCOME;
    }

    protected function data(mixed $notifiable): array
    {
        $name = $notifiable instanceof Model ? $notifiable->getAttribute('name') : null;

        return ['name' => is_string($name) ? $name : '', 'url' => AccountRoutes::url('sunrice.account.dashboard', [], $this->lang)];
    }

    protected function build(MailMessage $mail, mixed $notifiable): MailMessage
    {
        $data = ['site' => self::site(), ...$this->data($notifiable)];

        return $mail
            ->greeting($this->text('greeting', $data))
            ->line($this->text('intro', $data))
            ->action($this->text('action'), (string) $data['url']);
    }
}
