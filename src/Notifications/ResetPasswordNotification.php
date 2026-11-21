<?php

declare(strict_types=1);

namespace Sunrice\Account\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Sunrice\Account\Routing\AccountRoutes;

/** The site's password reset link (not the admin's), in the language it was asked in. */
final class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token, public readonly string $lang) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $email = is_object($notifiable) && method_exists($notifiable, 'getEmailForPasswordReset') ? $notifiable->getEmailForPasswordReset() : '';
        $url = AccountRoutes::url('account.password.reset', ['token' => $this->token, 'email' => is_string($email) ? $email : ''], $this->lang);
        $broker = config('sunrice-account.auth.passwords') ?: config('auth.defaults.passwords', 'users');
        $minutes = config('auth.passwords.'.(is_string($broker) ? $broker : 'users').'.expire', 60);

        return (new MailMessage)
            ->subject(__('account::auth.reset.mail.subject', ['site' => self::site()], $this->lang))
            ->line(__('account::auth.reset.mail.intro', [], $this->lang))
            ->action(__('account::auth.reset.mail.action', [], $this->lang), $url)
            ->line(__('account::auth.reset.mail.expires', ['minutes' => is_numeric($minutes) ? (int) $minutes : 60], $this->lang))
            ->line(__('account::auth.reset.mail.ignore', [], $this->lang));
    }

    private static function site(): string
    {
        $name = config('app.name');

        return is_string($name) ? $name : '';
    }
}
