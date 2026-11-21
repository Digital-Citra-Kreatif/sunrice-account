<?php

declare(strict_types=1);

namespace Sunrice\Account\Emails;

/**
 * An email the account package (or a plugin built on it) sends: who it
 * goes to, whether it can be switched off, and where its default texts
 * are (`{$lang}.subject`, `.intro`…, per language). Its subject can be
 * set per language in the settings, and its body replaced by the app's
 * resources/views/account/emails/{key}.blade.php.
 *
 * @api
 */
final readonly class EmailDefinition
{
    /**
     * @param  'member'|'staff'  $audience
     * @param  string  $lang  the translation key prefix of its default texts
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $lang,
        public string $audience = 'member',
        public bool $locked = false,
        public bool $enabledByDefault = true,
        public string $description = '',
    ) {}
}
