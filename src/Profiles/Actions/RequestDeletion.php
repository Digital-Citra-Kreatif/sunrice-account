<?php

declare(strict_types=1);

namespace Sunrice\Account\Profiles\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Sunrice\Account\Events\DeletionRequested;
use Sunrice\Account\Profiles\Models\Profile;

/** An account asks to be deleted (once): staff are told and handle it in the admin. */
final class RequestDeletion
{
    /** @return bool whether it was asked just now (false when it already was) */
    public function handle(Authenticatable $user): bool
    {
        $profile = Profile::forUser($user);
        if ($profile->deletion_requested_at !== null) {
            return false;
        }
        $profile->forceFill(['deletion_requested_at' => now()])->save();
        event(new DeletionRequested($user, $profile));

        return true;
    }
}
