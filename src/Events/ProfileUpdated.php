<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sunrice\Account\Profiles\Models\Profile;

/**
 * An account saved its profile page. `$changed` names what changed
 * (name, email, phone, accepts_marketing, fields.{key}).
 *
 * @api
 */
final class ProfileUpdated
{
    /** @param  list<string>  $changed */
    public function __construct(public readonly Authenticatable $user, public readonly Profile $profile, public readonly array $changed) {}
}
