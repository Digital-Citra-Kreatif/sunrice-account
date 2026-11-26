<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Health\AccountHealth;
use Sunrice\Account\Health\Check;

/** @return array<string, string> key => status */
function healthStatuses(): array
{
    app()->forgetScopedInstances();
    $statuses = [];
    foreach (app(AccountHealth::class)->checks() as $check) {
        $statuses[$check->key] = $check->status;
    }

    return $statuses;
}

function healthMessage(string $key): string
{
    foreach (app(AccountHealth::class)->checks() as $check) {
        if ($check->key === $key) {
            return $check->message;
        }
    }

    return '';
}

beforeEach(function () {
    Role::findOrCreate('member', 'web');
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);
});

it('finds a set-up app healthy', function () {
    expect(healthStatuses())->toBe([
        'user-model' => Check::OK, 'tables' => Check::OK, 'roles' => Check::OK, 'login-codes' => Check::OK,
        'google' => Check::OK, 'mail' => Check::OK, 'guard' => Check::OK,
    ])->and(app(AccountHealth::class)->healthy())->toBeTrue()
        ->and(app(AccountHealth::class)->problems())->toBe([]);

    $this->artisan('account:check')->assertSuccessful()->expectsOutputToContain('User model');
});

it('warns when emails go nowhere, a default role is missing or the guard is shared', function () {
    config(['mail.default' => 'log', 'mail.mailers.log.transport' => 'log']);
    accountSettings(['defaultRoles' => ['member', 'vip']]);
    expect(healthStatuses())->toMatchArray(['mail' => Check::WARNING, 'roles' => Check::WARNING])
        ->and(healthMessage('roles'))->toContain('vip')->toContain('sunrice:roles')
        ->and(healthMessage('mail'))->toContain('MAIL_MAILER');

    config(['sunrice-account.auth.guard' => 'web']);
    expect(healthStatuses()['guard'])->toBe(Check::WARNING);

    // Warnings don't fail the command.
    $this->artisan('account:check')->assertSuccessful();
});

it('fails on a default role with permissions, and on sign-in methods that can\'t work', function () {
    Role::findOrCreate('vip', 'web')->givePermissionTo(Permission::findOrCreate('shop.orders.view', 'web'));
    accountSettings(['defaultRoles' => ['vip']]);
    expect(healthStatuses()['roles'])->toBe(Check::ERROR)
        ->and(app(AccountHealth::class)->healthy())->toBeFalse();
    $this->artisan('account:check')->assertFailed();

    accountSettings(['defaultRoles' => ['member'], 'loginCode' => true, 'googleLogin' => true]);
    config(['services.google' => null, 'sunrice-account.google' => []]);
    expect(healthStatuses())->toMatchArray(['roles' => Check::OK, 'login-codes' => Check::OK, 'google' => Check::WARNING]);

    Schema::shouldReceive('hasColumn')->andReturnTrue();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $table !== 'one_time_passwords');
    expect(healthStatuses()['login-codes'])->toBe(Check::ERROR)
        ->and(healthMessage('login-codes'))->toContain('one_time_passwords')
        ->and(healthStatuses()['tables'])->toBe(Check::OK);
});

it('fails when the account tables aren\'t migrated', function () {
    // Looked up, not dropped: a schema change would outlive the test on MySQL.
    Schema::shouldReceive('hasTable')->andReturnTrue();
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $column !== 'anonymized_at');

    expect(healthStatuses()['tables'])->toBe(Check::ERROR)
        ->and(healthMessage('tables'))->toContain('php artisan migrate');
});

it('installs: migrations, roles, then the checks', function () {
    Role::query()->where('name', 'member')->delete();

    $this->artisan('account:install')->assertSuccessful()->expectsOutputToContain('AccountUserResource');

    expect(Role::query()->where('name', 'member')->where('guard_name', 'web')->exists())->toBeTrue();
});
