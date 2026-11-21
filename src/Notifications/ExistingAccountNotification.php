<?php

declare(strict_types=1);

namespace Sunrice\Account\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Sunrice\Account\Routing\AccountRoutes;

/**
 * Someone tried to register with an email that has an account: its owner
 * is told, with the way in, instead of the form saying the email is taken.
 */
final class ExistingAccountNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $lang) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $name = config('app.name');

        return (new MailMessage)
            ->subject(__('account::auth.register.existing.subject', ['site' => is_string($name) ? $name : ''], $this->lang))
            ->line(__('account::auth.register.existing.intro', [], $this->lang))
            ->action(__('account::auth.register.existing.action', [], $this->lang), AccountRoutes::url('account.login', [], $this->lang))
            ->line(__('account::auth.register.existing.forgot', ['url' => AccountRoutes::url('account.password.request', [], $this->lang)], $this->lang))
            ->line(__('account::auth.register.existing.ignore', [], $this->lang));
    }
}
