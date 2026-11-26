<?php

declare(strict_types=1);

namespace Sunrice\Account\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sunrice\Account\Profiles\Models\Profile;

/**
 * Staff removed an account's personal data: its name, email, phone,
 * sign-ins and roles are gone, and the user row stays for what points at
 * it. Plugins clear what they keep about the person (`$email` is the
 * address the account had, to find records kept by email). Dispatched
 * inside the transaction: a listener that throws undoes it all.
 *
 * @api
 */
final class Anonymized
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly Profile $profile,
        public readonly string $email,
    ) {}
}
