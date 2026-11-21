<?php

declare(strict_types=1);

namespace Sunrice\Account\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LogicException;
use Sunrice\Admin\Panel;

/**
 * The base of every account email: queued, in one language, sent while
 * its switch is on (Settings → Accounts → Emails; locked ones always),
 * with its subject from the settings for that language or its default
 * text, and the site's brand. An app replaces an email's body with
 * resources/views/account/emails/{key}.blade.php (a markdown mail view;
 * it gets `$lang`, `$site` and what the email passes as data).
 *
 * Plugins build their own account emails on it, after registering their
 * definition with Account::emails()->register().
 *
 * @api
 */
abstract class AccountEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $lang) {}

    /** The email's key in Account::emails(). */
    abstract public static function key(): string;

    /** The lines, button… of the default body. */
    abstract protected function build(MailMessage $mail, mixed $notifiable): MailMessage;

    /**
     * Placeholders of the subject (`:site` is always there) and data of a replacement view.
     *
     * @return array<string, string|int>
     */
    protected function data(mixed $notifiable): array
    {
        return [];
    }

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return app(EmailSettings::class)->enabled(static::key()) ? ['mail'] : [];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $data = ['site' => self::site(), ...$this->data($notifiable)];
        $mail = $this->build((new MailMessage)->subject($this->subject($data)), $notifiable);
        $replacement = 'account.emails.'.static::key();

        return view()->exists($replacement)
            ? $mail->markdown($replacement, [...$data, 'lang' => $this->lang])
            : $mail->markdown('account::mail.notification', ['lang' => $this->lang]);
    }

    /**
     * A text of the email in its language: `$this->text('intro')` reads `{lang}.intro`.
     *
     * @param  array<string, string|int>  $replace
     */
    protected function text(string $part, array $replace = []): string
    {
        $text = __($this->definition()->lang.'.'.$part, $replace, $this->lang);

        return is_string($text) ? $text : '';
    }

    /** The site's name, as the emails show it (the panel's mail brand). */
    public static function site(): string
    {
        return app(Panel::class)->mailBrand()->name;
    }

    /** @param  array<string, string|int>  $data */
    private function subject(array $data): string
    {
        $stored = app(EmailSettings::class)->subject(static::key(), $this->lang);
        if ($stored === null) {
            return $this->text('subject', $data);
        }
        foreach ($data as $name => $value) {
            $stored = str_replace([':'.$name, '{'.$name.'}'], (string) $value, $stored);
        }

        return $stored;
    }

    private function definition(): EmailDefinition
    {
        return app(Emails::class)->get(static::key()) ?? throw new LogicException('The account email "'.static::key().'" isn\'t registered.');
    }
}
