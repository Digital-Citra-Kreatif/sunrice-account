<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account signed in on the site (Laravel's Login fires too, with the
 * account guard): plugins merge a guest cart, link guest orders…
 *
 * @api
 */
final class SignedIn
{
    /** @param  string  $method  "password" (and later "code", "google", "registration", "verification") */
    public function __construct(public readonly Authenticatable $user, public readonly string $method, public readonly bool $remember = false) {}
}
