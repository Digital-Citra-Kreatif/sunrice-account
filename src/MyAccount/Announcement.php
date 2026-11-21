<?php

declare(strict_types=1);

namespace Sunrice\Account\MyAccount;

use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Cms\Support\Locales;

/**
 * An optional announcement in My Account (settings `announcement`):
 * as its first page (`tab`, which /account then opens), or above or below
 * every account page. Its title and message are per language, falling back
 * to the main language's.
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
        if (! $this->settings->boolean('announcement.enabled') || $this->message() === '') {
            return null;
        }
        $position = $this->settings->string('announcement.position', 'above');

        return in_array($position, self::POSITIONS, true) ? $position : null;
    }

    public function title(): string
    {
        $title = $this->localized('title');

        return $title !== '' ? $title : (string) __('account::account.announcement.title');
    }

    public function message(): string
    {
        return $this->localized('message');
    }

    private function localized(string $part): string
    {
        $values = $this->settings->get("announcement.{$part}", []);
        $values = is_array($values) ? $values : [];
        foreach ([app()->getLocale(), Locales::default()] as $locale) {
            $value = $values[$locale] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
