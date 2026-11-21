<?php

declare(strict_types=1);

namespace Sunrice\Account\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LogicException;
use Sunrice\Account\Auth\Verification;

/** The link that verifies an account's email address, in the language it was sent in. */
final class VerifyEmailNotification extends Notification implements ShouldQueue
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
        if (! $notifiable instanceof Model || ! $notifiable instanceof Authenticatable) {
            throw new LogicException('Only users verify their email.');
        }
        $name = config('app.name');

        return (new MailMessage)
            ->subject(__('account::auth.verify.mail.subject', ['site' => is_string($name) ? $name : ''], $this->lang))
            ->line(__('account::auth.verify.mail.intro', [], $this->lang))
            ->action(__('account::auth.verify.mail.action', [], $this->lang), Verification::url($notifiable, $this->lang))
            ->line(__('account::auth.verify.mail.ignore', [], $this->lang));
    }
}
