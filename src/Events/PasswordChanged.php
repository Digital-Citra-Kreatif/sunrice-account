<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account changed (or first set) its password on its profile page.
 *
 * @api
 */
final class PasswordChanged
{
    public function __construct(public readonly Authenticatable $user) {}
}
