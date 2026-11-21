<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

/**
 * Before an account is created. Listeners may change `$data` (name,
 * email, phone, accepts_marketing, and what other plugins' fields add),
 * or refuse the registration by throwing a ValidationException, whose
 * messages show on the form.
 *
 * @api
 */
final class Registering
{
    /** @param  array<string, mixed>  $data  the validated input, without the password */
    public function __construct(public array $data, public readonly string $locale) {}
}
