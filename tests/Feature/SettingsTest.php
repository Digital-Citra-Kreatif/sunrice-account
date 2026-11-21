<?php

declare(strict_types=1);

use Sunrice\Account\Facades\Account;
use Sunrice\Account\Settings\AccountSettings;

it('reads the config defaults until settings are saved', function () {
    $settings = app(AccountSettings::class);

    expect($settings->boolean('general.registration'))->toBeTrue()
        ->and($settings->boolean('general.verify_email'))->toBeTrue()
        ->and($settings->strings('general.default_roles'))->toBe(['member'])
        ->and($settings->stored())->toBe([]);
});

it('saves a section over the defaults, keeping the keys not given', function () {
    Account::settings()->saveSection('general', ['registration' => false]);
    app()->forgetScopedInstances();

    $settings = Account::settings();
    expect($settings->boolean('general.registration'))->toBeFalse()
        ->and($settings->boolean('general.verify_email'))->toBeTrue()
        ->and($settings->stored())->toBe(['general' => ['registration' => false]]);
});

it('keeps sections of other plugins apart', function () {
    $settings = Account::settings();
    $settings->saveSection('general', ['verify_email' => false]);
    $settings->saveSection('shop', ['customer_roles' => ['customer']]);

    expect($settings->get('general.verify_email'))->toBeFalse()
        ->and($settings->strings('shop.customer_roles'))->toBe(['customer'])
        ->and($settings->section('general'))->toMatchArray(['registration' => true, 'verify_email' => false]);
});

it('replaces lists as a whole', function () {
    Account::settings()->saveSection('general', ['default_roles' => ['member', 'reseller']]);

    expect(Account::settings()->strings('general.default_roles'))->toBe(['member', 'reseller']);
});
