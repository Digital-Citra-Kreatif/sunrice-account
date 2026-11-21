<?php

declare(strict_types=1);

namespace Sunrice\Account\Support;

/** @internal Typed reads of validated input. */
final class Input
{
    /** @param  array<array-key, mixed>  $data */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param  array<array-key, mixed>  $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = self::string($data, $key);

        return $value === '' ? null : $value;
    }
}
