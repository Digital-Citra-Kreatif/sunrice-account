<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account signed out on the site.
 *
 * @api
 */
final class SignedOut
{
    public function __construct(public readonly Authenticatable $user) {}
}
