<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sunrice\Account\Profiles\Models\Profile;

/**
 * An account asked to be deleted; staff are told, and handle it.
 *
 * @api
 */
final class DeletionRequested
{
    public function __construct(public readonly Authenticatable $user, public readonly Profile $profile) {}
}
