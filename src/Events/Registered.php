<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sunrice\Account\Profiles\Models\Profile;

/**
 * An account was created on the site (after Laravel's own Registered).
 *
 * @api
 */
final class Registered
{
    /** @param  array<string, mixed>  $data  what was registered, without the password */
    public function __construct(
        public readonly Authenticatable $user,
        public readonly Profile $profile,
        public readonly array $data,
        public readonly string $locale,
    ) {}
}
