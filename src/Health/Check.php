<?php

declare(strict_types=1);

namespace Sunrice\Account\Health;

/**
 * One thing accounts need from the app, and how it stands: fine, worth a
 * look (a warning: accounts work, something is off) or broken (an error:
 * a sign-in flow fails).
 *
 * @api
 */
final readonly class Check
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const ERROR = 'error';

    private function __construct(public string $key, public string $label, public string $status, public string $message) {}

    public static function ok(string $key, string $label, string $message = ''): self
    {
        return new self($key, $label, self::OK, $message);
    }

    /** Accounts work, but something isn't as it should be: say what to do. */
    public static function warning(string $key, string $label, string $message): self
    {
        return new self($key, $label, self::WARNING, $message);
    }

    /** Something accounts need is missing: say what to do. */
    public static function error(string $key, string $label, string $message): self
    {
        return new self($key, $label, self::ERROR, $message);
    }

    public function passed(): bool
    {
        return $this->status === self::OK;
    }
}
