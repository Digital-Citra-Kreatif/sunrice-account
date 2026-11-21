<?php

declare(strict_types=1);

namespace Sunrice\Account\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Sunrice\Account\Emails\AccountEmail;
use Sunrice\Account\Emails\Emails;

/** To staff: someone created an account (in the site's main language). */
final class NewRegistrationNotification extends AccountEmail
{
    public function __construct(public readonly string $name, public readonly string $email, string $lang)
    {
        parent::__construct($lang);
    }

    public static function key(): string
    {
        return Emails::STAFF_REGISTRATION;
    }

    protected function data(mixed $notifiable): array
    {
        return ['name' => $this->name, 'email' => $this->email];
    }

    protected function build(MailMessage $mail, mixed $notifiable): MailMessage
    {
        return $mail
            ->line($this->text('intro', ['site' => self::site()]))
            ->line($this->text('details', ['name' => $this->name, 'email' => $this->email]));
    }
}
