<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Http\Middleware\Authenticate;
use Sunrice\Account\Routing\AccountRoutes;

it('shows My Account to a signed-in account, in the language of the address', function () {
    $this->actingAs(siteUser(), 'account');

    $this->get('/account')->assertOk()->assertSee('Akun saya')->assertSee('Halo, Ana.');
    $this->get('/en/account')->assertOk()->assertSee('My account')->assertSee('Hello, Ana.');
});

it('keeps account pages out of search engines and shared caches', function () {
    $this->actingAs(siteUser(), 'account');

    $this->get('/account')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('<meta name="robots"', false);
    expect($this->get('/account')->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('sends guests to the sign-in page, then back where they were going', function () {
    $this->get('/account?tab=1')->assertRedirect('/account/login')
        ->assertSessionHas(Authenticate::INTENDED, 'http://localhost/account?tab=1');
    $this->get('/en/account')->assertRedirect('/en/account/login');
});

it('doesn\'t let an admin session into My Account', function () {
    $this->actingAs(siteUser(), 'web');

    $this->get('/account')->assertRedirect('/account/login');
});

it('adds other plugins\' pages under the account prefix, in every language', function () {
    Account::routes(function (string $suffix): void {
        Route::get('orders', fn () => 'my orders')->name('test.orders'.$suffix);
    });
    Account::routes(function (string $suffix): void {
        Route::get('help', fn () => 'help for all')->name('test.help'.$suffix);
    }, signedIn: false);

    $this->get('/account/orders')->assertRedirect('/account/login');
    $this->get('/account/help')->assertOk()->assertSee('help for all')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->actingAs(siteUser(), 'account');
    $this->get('/account/orders')->assertOk()->assertSee('my orders');
    $this->get('/en/account/orders')->assertOk();
    expect(Account::url('test.orders', locale: 'en'))->toBe('http://localhost/en/account/orders')
        ->and(Account::url('test.orders', locale: 'id'))->toBe('http://localhost/account/orders');
});

it('sends signed-in accounts away from guest-only pages', function () {
    Account::routes(function (string $suffix): void {
        Route::get('join', fn () => 'join')->middleware('sunrice.account.guest')->name('test.join'.$suffix);
    }, signedIn: false);
    $this->get('/account/join')->assertOk();

    $this->actingAs(siteUser(), 'account');
    $this->get('/account/join')->assertRedirect('/account');
    $this->get('/en/account/join')->assertRedirect('/en/account');
});

it('takes the account prefix from the settings page, else from config', function () {
    expect(AccountRoutes::prefix())->toBe('account');

    config(['sunrice-account.paths.prefix' => '/members/']);
    expect(AccountRoutes::prefix())->toBe('members');

    accountSettings(['path' => 'my-account']);
    expect(AccountRoutes::prefix())->toBe('my-account')
        ->and(AccountRoutes::path('login'))->toBe('login');
});

it('names its routes sunrice.account.*, leaving an app\'s own account.* names alone', function () {
    Route::middleware('web')->get('/shop/my-account/login', fn () => 'the app\'s')->name('account.login');
    app('router')->getRoutes()->refreshNameLookups();

    expect(route('account.login'))->toBe('http://localhost/shop/my-account/login')
        ->and(Account::url('sunrice.account.login', locale: 'id'))->toBe('http://localhost/account/login');
});
