<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;
use Sunrice\Account\AccountPlugin;
use Sunrice\Admin\Actions\Permissions\SeedRoles;
use Sunrice\Admin\Panel;

it('declares the account permissions', function () {
    expect(app(Panel::class)->getPermissions())->toHaveKey('account.settings.accounts');
});

it('seeds the member role without any admin permission', function () {
    app(SeedRoles::class)->handle();

    $role = Role::query()->where('name', AccountPlugin::MEMBER_ROLE)->where('guard_name', app(Panel::class)->getGuard())->first();
    expect($role)->not->toBeNull()
        ->and($role?->permissions()->count())->toBe(0);
});

it('keeps members out of the admin', function () {
    $user = siteUser();
    $user->assignRole(Role::findOrCreate(AccountPlugin::MEMBER_ROLE, 'web'));

    $this->actingAs($user, 'web')->get('/admin')->assertForbidden();
});
