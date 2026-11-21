<?php

declare(strict_types=1);

namespace Sunrice\Account\Support;

/**
 * The fields' names in validation messages, in the page's language
 * ("Nama wajib diisi", not "name wajib diisi"), plugins' fields by label.
 *
 * @internal
 */
final class FieldNames
{
    /**
     * @param  list<array{key: string, label: string}|array<string, mixed>>  $fields  plugins' fields (MyAccount\Fields)
     * @return array<string, string>
     */
    public static function all(array $fields = [], bool $newPassword = false): array
    {
        $names = [
            'name' => self::line('account::auth.fields.name'),
            'email' => self::line('account::auth.fields.email'),
            'phone' => self::line('account::auth.fields.phone'),
            'password' => self::line($newPassword ? 'account::auth.fields.new_password' : 'account::auth.fields.password'),
            'password_confirmation' => self::line('account::auth.fields.password_confirmation'),
            'current_password' => self::line('account::account.profile.current_password'),
            'terms' => self::line('account::auth.register.terms_name'),
        ];
        foreach ($fields as $field) {
            if (is_string($field['key'] ?? null) && is_string($field['label'] ?? null)) {
                $names['fields.'.$field['key']] = $field['label'];
            }
        }

        return $names;
    }

    private static function line(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }
}
