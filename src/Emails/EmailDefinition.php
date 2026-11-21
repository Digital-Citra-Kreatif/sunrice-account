<?php

declare(strict_types=1);

namespace Sunrice\Account\Emails;

use Closure;

/**
 * An email the account package (or a plugin built on it) sends: who it
 * goes to, whether it can be switched off, and where its default texts
 * are (`{$lang}.subject`, `.intro`…, per language). The package's own
 * emails are set up in Settings → Accounts → Emails; a plugin's email
 * gives its settings with `$settings` (from wherever the plugin keeps
 * them), else it's on (unless `$enabledByDefault` is false) with its
 * default subject. Its body can be replaced by the app's
 * resources/views/account/emails/{key}.blade.php.
 *
 * @api
 */
final readonly class EmailDefinition
{
    /**
     * `$lang` is the translation key prefix of its default texts. `$settings`
     * gives a plugin email's settings: its subject in "code: subject"
     * lines, and roles null for the default (super admins and administrators).
     *
     * @param  'member'|'staff'  $audience
     * @param  (Closure(): array{enabled?: bool, subject?: string, addresses?: list<string>, roles?: list<string>|null})|null  $settings
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $lang,
        public string $audience = 'member',
        public bool $locked = false,
        public bool $enabledByDefault = true,
        public string $description = '',
        public ?Closure $settings = null,
    ) {}
}
