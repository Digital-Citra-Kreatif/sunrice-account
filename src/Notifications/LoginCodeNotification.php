<?php

declare(strict_types=1);

namespace Sunrice\Account\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Sunrice\Account\Emails\AccountEmail;
use Sunrice\Account\Emails\Emails;

/** An emailed sign-in code. */
final class LoginCodeNotification extends AccountEmail
{
    public function __construct(#[\SensitiveParameter] public readonly string $code, public readonly int $minutes, string $lang)
    {
        parent::__construct($lang);
    }

    public static function key(): string
    {
        return Emails::LOGIN_CODE;
    }

    protected function data(mixed $notifiable): array
    {
        return ['code' => $this->code, 'minutes' => $this->minutes];
    }

    protected function build(MailMessage $mail, mixed $notifiable): MailMessage
    {
        return $mail
            ->line($this->text('intro'))
            ->line('**'.$this->code.'**')
            ->line($this->text('expires', ['minutes' => $this->minutes]))
            ->line($this->text('ignore'));
    }
}
