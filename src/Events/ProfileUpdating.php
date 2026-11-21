<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Before an account's profile page is saved. Listeners may change `$data`
 * (name, email, phone, accepts_marketing, and `fields`: the profile fields
 * plugins add), or refuse the save by throwing a ValidationException.
 *
 * @api
 */
final class ProfileUpdating
{
    /** @param  array<string, mixed>  $data */
    public function __construct(public readonly Authenticatable $user, public array $data) {}
}
