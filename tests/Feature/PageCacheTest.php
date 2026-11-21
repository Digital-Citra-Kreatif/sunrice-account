<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Sunrice\Account\Site\AccountPageCache;

it('never keeps account pages', function (string $path) {
    expect(AccountPageCache::skips(Request::create($path)))->toBeTrue();
})->with(['/account', '/account/orders', '/en/account', '/en/account/profile']);

it('keeps the site\'s pages for guests', function (string $path) {
    expect(AccountPageCache::skips(Request::create($path)))->toBeFalse();
})->with(['/', '/about', '/accounting', '/en/accounts']);

it('gives a signed-in account fresh pages everywhere', function () {
    $request = Request::create('/about');
    $request->setLaravelSession(app('session.store'));
    expect(AccountPageCache::skips($request))->toBeFalse();

    $this->actingAs(siteUser(), 'account');
    expect(AccountPageCache::skips($request))->toBeTrue();
});
