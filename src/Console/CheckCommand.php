<?php

declare(strict_types=1);

namespace Sunrice\Account\Console;

use Illuminate\Console\Command;
use Sunrice\Account\Health\AccountHealth;
use Sunrice\Account\Health\Check;

/**
 * `php artisan account:check`: whether the app has what accounts need
 * (AccountHealth). Fails (exit code 1) when something is broken, so a
 * deploy script can stop on it; warnings don't fail it.
 */
final class CheckCommand extends Command
{
    protected $signature = 'account:check';

    protected $description = 'Check what site accounts need: the user model, tables, roles, sign-in methods and mail';

    public function handle(AccountHealth $health): int
    {
        self::report($this, $health);

        return $health->healthy() ? self::SUCCESS : self::FAILURE;
    }

    /** Prints every check, then what to do about those that aren't fine. */
    public static function report(Command $command, AccountHealth $health): void
    {
        foreach ($health->checks() as $check) {
            $command->outputComponents()->twoColumnDetail($check->label, match ($check->status) {
                Check::OK => '<fg=green>'.($check->message === '' ? 'OK' : $check->message).'</>',
                Check::WARNING => '<fg=yellow>CHECK</>',
                default => '<fg=red>MISSING</>',
            });
        }
        foreach ($health->problems() as $check) {
            $check->status === Check::ERROR
                ? $command->outputComponents()->error("{$check->label}: {$check->message}")
                : $command->outputComponents()->warn("{$check->label}: {$check->message}");
        }
    }
}
