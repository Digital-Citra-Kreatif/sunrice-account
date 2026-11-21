<?php

declare(strict_types=1);

namespace Sunrice\Account\MyAccount;

use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Cms\Support\Locales;

/**
 * An optional announcement in My Account (Settings → Accounts):
 * as its first page (`tab`, which /account then opens), or above or below
 * every account page. Its title and message are per language, falling back
 * to the main language's (other languages' messages are one line each).
 *
 * @api
 */
final class Announcement
{
    public const POSITIONS = ['tab', 'above', 'below'];

    public function __construct(private AccountSettings $settings) {}

    /** Where it shows, or null when it's off or empty. */
    public function position(): ?string
    {
        if (! $this->settings->announcement || $this->message() === '') {
            return null;
        }
        $position = $this->settings->announcementPosition;

        return in_array($position, self::POSITIONS, true) ? $position : null;
    }

    public function title(): string
    {
        $title = $this->localized($this->settings->announcementTitle, $this->settings->announcementTitles);

        return $title !== '' ? $title : (string) __('account::account.announcement.title');
    }

    public function message(): string
    {
        return $this->localized($this->settings->announcementMessage, $this->settings->announcementMessages);
    }

    /** The current language's line, else the main language's text. */
    private function localized(string $main, string $others): string
    {
        $locale = app()->getLocale();
        $text = $locale !== Locales::default() ? AccountSettings::line($others, $locale) : '';

        return $text !== '' ? $text : trim($main);
    }
}
