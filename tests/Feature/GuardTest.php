<?php

declare(strict_types=1);

use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Facades\Account;
use Sunrice\Admin\Panel;

it('defines the account guard on the user provider of the panel\'s guard', function () {
    expect(config('auth.guards.account'))->toBe(['driver' => 'session', 'provider' => config('auth.guards.web.provider')])
        ->and(Account::guard())->toBe('account')
        ->and(SiteGuard::sharesPanelGuard())->toBeFalse();
});

it('follows a panel on a guard of its own', function () {
    config(['auth.guards.account' => null, 'auth.guards.staff' => ['driver' => 'session', 'provider' => 'staff_users']]);
    app()->forgetInstance(Panel::class);
    app()->singleton(Panel::class, fn (): Panel => Panel::make()->guard('staff'));
    app(Panel::class);

    expect(config('auth.guards.account'))->toBe(['driver' => 'session', 'provider' => 'staff_users']);
});

it('leaves a guard the app defines alone', function () {
    config(['auth.guards.account' => ['driver' => 'session', 'provider' => 'members']]);
    SiteGuard::define(app(Panel::class));

    expect(config('auth.guards.account'))->toBe(['driver' => 'session', 'provider' => 'members']);
});

it('signs in on the site without signing in to the admin', function () {
    $user = siteUser();
    SiteGuard::get()->login($user);

    expect(Account::user()?->getAuthIdentifier())->toBe($user->id);
    $this->assertAuthenticated('account');
    $this->assertGuest('web');
});

it('knows when the site shares the admin\'s guard', function () {
    config(['sunrice-account.auth.guard' => 'web']);

    expect(SiteGuard::sharesPanelGuard())->toBeTrue();
});
