<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account chose a new password with a reset link.
 *
 * @api
 */
final class PasswordReset
{
    public function __construct(public readonly Authenticatable $user) {}
}
