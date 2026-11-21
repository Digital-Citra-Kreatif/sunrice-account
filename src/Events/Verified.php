<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account verified its email address with its link (plugins link the
 * guest orders made with it…).
 *
 * @api
 */
final class Verified
{
    public function __construct(public readonly Authenticatable $user, public readonly string $locale) {}
}
