<?php

declare(strict_types=1);

namespace Sunrice\Account\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Sunrice\Account\Emails\AccountEmail;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Routing\AccountRoutes;

/** The site's password reset link (not the admin's), in the language it was asked in. */
final class ResetPasswordNotification extends AccountEmail
{
    public function __construct(#[\SensitiveParameter] public readonly string $token, string $lang)
    {
        parent::__construct($lang);
    }

    public static function key(): string
    {
        return Emails::RESET;
    }

    protected function build(MailMessage $mail, mixed $notifiable): MailMessage
    {
        $email = is_object($notifiable) && method_exists($notifiable, 'getEmailForPasswordReset') ? $notifiable->getEmailForPasswordReset() : '';
        $url = AccountRoutes::url('account.password.reset', ['token' => $this->token, 'email' => is_string($email) ? $email : ''], $this->lang);
        $broker = config('sunrice-account.auth.passwords') ?: config('auth.defaults.passwords', 'users');
        $minutes = config('auth.passwords.'.(is_string($broker) ? $broker : 'users').'.expire', 60);

        return $mail
            ->line($this->text('intro'))
            ->action($this->text('action'), $url)
            ->line($this->text('expires', ['minutes' => is_numeric($minutes) ? (int) $minutes : 60]))
            ->line($this->text('ignore'));
    }
}
