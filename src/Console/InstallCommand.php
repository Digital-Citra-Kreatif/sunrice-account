<?php

declare(strict_types=1);

namespace Sunrice\Account\Console;

use Illuminate\Console\Command;
use Sunrice\Account\Admin\AccountUserResource;
use Sunrice\Account\Health\AccountHealth;

/**
 * `php artisan account:install`: what accounts need after
 * `composer require sunrice/account`, safe to run again: the migrations
 * (and with them the permissions), the default roles (`member`), then the
 * checks of `account:check`. The plugin itself is added to the panel by
 * `sunrice:install --plugins`, or by hand in the panel provider.
 */
final class InstallCommand extends Command
{
    protected $signature = 'account:install';

    protected $description = 'Install site accounts: migrations, permissions, the member role, and a check of what they need';

    public function handle(AccountHealth $health): int
    {
        $this->components->task('Migrations', fn (): bool => $this->callSilently('migrate', ['--force' => true]) === 0);
        $this->components->task('Roles', fn (): bool => $this->callSilently('sunrice:roles') === 0);
        $this->newLine();

        CheckCommand::report($this, $health);

        $this->newLine();
        $this->components->info('Users lists staff only once your panel provider has: $panel->userResource(\\'.AccountUserResource::class.'::class). Accounts are in Administration → Accounts.');

        return $health->healthy() ? self::SUCCESS : self::FAILURE;
    }
}
