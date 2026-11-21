<?php

declare(strict_types=1);

use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Account\Tests\TestCase;
use Workbench\App\Models\User;

uses(TestCase::class)->in('Feature');

/** A site user (no roles), verified unless said otherwise. */
function siteUser(array $attributes = []): User
{
    return User::query()->create([...['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret-pass', 'email_verified_at' => now()], ...$attributes]);
}

/** Saves account settings (Settings → Accounts), as the settings page does: the others keep their values. */
function accountSettings(array $values): void
{
    app(AccountSettings::class)->fill($values)->save();
}
