<?php

declare(strict_types=1);

namespace Sunrice\Account\MyAccount;

use Closure;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Sunrice\Account\Profiles\Models\Profile;

/**
 * Extra fields plugins add to the registration form and the profile page
 * (Account::registrationFields(), Account::profileFields()). Their values
 * are kept in the profile's `meta`, under each field's key.
 *
 * A field: ['key' => 'shop_company', 'label' => 'Company', 'type' => 'text'|'textarea'|'select'|'checkbox',
 * 'rules' => ['nullable', 'string', 'max:120'], 'options' => ['a' => 'A'] (select), 'help' => '…'].
 * `label`, `help` and option labels may be closures (translations).
 *
 * @api
 */
final class Fields
{
    public const TYPES = ['text', 'textarea', 'select', 'checkbox'];

    /** @var list<Closure(): list<array<string, mixed>>> */
    private array $registration = [];

    /** @var list<Closure(): list<array<string, mixed>>> */
    private array $profile = [];

    /** @param  Closure(): list<array<string, mixed>>  $fields */
    public function addToRegistration(Closure $fields): void
    {
        $this->registration[] = $fields;
    }

    /** @param  Closure(): list<array<string, mixed>>  $fields */
    public function addToProfile(Closure $fields): void
    {
        $this->profile[] = $fields;
    }

    /** @return list<array{key: string, label: string, type: string, rules: list<mixed>, options: array<string, string>, help: string}> */
    public function registration(): array
    {
        return $this->resolve($this->registration);
    }

    /** @return list<array{key: string, label: string, type: string, rules: list<mixed>, options: array<string, string>, help: string}> */
    public function profile(): array
    {
        return $this->resolve($this->profile);
    }

    /**
     * Validation rules for the fields, under `fields.{key}`.
     *
     * @param  list<array{key: string, label: string, type: string, rules: list<mixed>, options: array<string, string>, help: string}>  $fields
     * @return array<string, list<mixed>>
     */
    public static function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $own = $field['rules'] !== [] ? $field['rules'] : ['nullable'];
            if ($field['type'] === 'select' && $field['options'] !== []) {
                $own[] = Rule::in(array_keys($field['options']));
            }
            if ($field['type'] === 'checkbox') {
                $own[] = 'boolean';
            }
            $rules['fields.'.$field['key']] = $own;
        }

        return $rules;
    }

    /**
     * The validated values of the fields, a checkbox left unticked as false.
     *
     * @param  list<array{key: string, label: string, type: string, rules: list<mixed>, options: array<string, string>, help: string}>  $fields
     * @param  array<array-key, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function values(array $fields, array $validated): array
    {
        $given = is_array($validated['fields'] ?? null) ? $validated['fields'] : [];
        $values = [];
        foreach ($fields as $field) {
            $value = $given[$field['key']] ?? null;
            $values[$field['key']] = $field['type'] === 'checkbox' ? (bool) $value : $value;
        }

        return $values;
    }

    /** @param  array<string, mixed>  $values */
    public static function store(Profile $profile, array $values): void
    {
        if ($values !== []) {
            $profile->forceFill(['meta' => [...($profile->meta ?? []), ...$values]])->save();
        }
    }

    /**
     * @param  list<Closure(): list<array<string, mixed>>>  $sources
     * @return list<array{key: string, label: string, type: string, rules: list<mixed>, options: array<string, string>, help: string}>
     */
    private function resolve(array $sources): array
    {
        $fields = [];
        foreach ($sources as $source) {
            foreach ($source() as $field) {
                $key = $field['key'] ?? null;
                $type = $field['type'] ?? 'text';
                if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1 || ! in_array($type, self::TYPES, true)) {
                    throw new InvalidArgumentException('An account field needs a key like "shop_company" and a type among '.implode(', ', self::TYPES).'.');
                }
                $options = [];
                foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $value => $label) {
                    $options[(string) $value] = self::text($label);
                }
                $fields[$key] = [
                    'key' => $key,
                    'label' => self::text($field['label'] ?? $key),
                    'type' => $type,
                    'rules' => array_values(is_array($field['rules'] ?? null) ? $field['rules'] : []),
                    'options' => $options,
                    'help' => self::text($field['help'] ?? ''),
                ];
            }
        }

        return array_values($fields);
    }

    private static function text(mixed $text): string
    {
        $text = $text instanceof Closure ? $text() : $text;

        return is_scalar($text) ? (string) $text : '';
    }
}
