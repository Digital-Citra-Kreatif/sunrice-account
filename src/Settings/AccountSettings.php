<?php

declare(strict_types=1);

namespace Sunrice\Account\Settings;

use Illuminate\Support\Arr;
use Sunrice\Account\Support\Defaults;
use Throwable;

/**
 * The account settings: one `account` row in the panel's settings table,
 * laid over the defaults in config('sunrice-account.defaults'). Read once
 * per request (one cached query for every settings row).
 *
 *     app(AccountSettings::class)->boolean('general.registration');
 *     app(AccountSettings::class)->section('general');
 *
 * Plugins built on accounts keep their own sections here too (commerce's
 * "checkout_accounts"…): saveSection() only touches the section it's given.
 *
 * @api
 */
final class AccountSettings
{
    public const KEY = 'account';

    /** @var array<array-key, mixed>|null */
    private ?array $resolved = null;

    /**
     * The stored values only (empty before anything is saved, or before
     * the tables exist).
     *
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        try {
            return (new StoredValues)->values;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<array-key, mixed> */
    public function all(): array
    {
        $defaults = config('sunrice-account.defaults', []);

        return $this->resolved ??= Defaults::merge(is_array($defaults) ? $defaults : [], $this->stored());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /** @return list<string> a list setting's values (roles, addresses…) */
    public function strings(string $key): array
    {
        $value = $this->get($key, []);

        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            is_array($value) ? $value : [$value],
        ), fn (string $item): bool => $item !== ''));
    }

    /** @return array<array-key, mixed> */
    public function section(string $section): array
    {
        $values = $this->get($section, []);

        return is_array($values) ? $values : [];
    }

    /**
     * Replace one section's stored values (keys not given keep their
     * stored or default values).
     *
     * @param  array<string, mixed>  $values
     */
    public function saveSection(string $section, array $values): void
    {
        $stored = $this->stored();
        $current = $stored[$section] ?? [];
        $stored[$section] = array_replace(is_array($current) ? $current : [], $values);
        $settings = new StoredValues;
        $settings->values = $stored;
        $settings->save();
        $this->flush();
    }

    /** Forget what this request has read (after a save, or between queued jobs). */
    public function flush(): void
    {
        $this->resolved = null;
    }
}
