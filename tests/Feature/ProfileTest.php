<?php

declare(strict_types=1);

use Sunrice\Account\Facades\Account;
use Sunrice\Account\Profiles\Models\Profile;

it('creates a user\'s profile on first need, once', function () {
    $user = siteUser();

    $profile = Account::profile($user);
    expect($profile->user_id)->toBe($user->id)
        ->and($profile->accepts_marketing)->toBeFalse()
        ->and($profile->password_set)->toBeTrue()
        ->and(Account::profile($user)->id)->toBe($profile->id)
        ->and(Profile::query()->count())->toBe(1);
});

it('keeps what plugins add under their own keys in meta', function () {
    $profile = Account::profile(siteUser());
    $profile->forceFill(['meta' => ['shop' => ['tax_id' => '01.234']]])->save();

    expect($profile->fresh()?->metaValue('shop.tax_id'))->toBe('01.234')
        ->and($profile->metaValue('shop.missing', 'none'))->toBe('none');
});
