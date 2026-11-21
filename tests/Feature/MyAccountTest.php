<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Sunrice\Account\Facades\Account;

beforeEach(function () {
    $this->user = siteUser();
    $this->actingAs($this->user, 'account');
});

it('opens on the dashboard, with the menu and sign-out', function () {
    $this->get('/account')->assertOk()
        ->assertSee('Halo, Ana.')
        ->assertSee('http://localhost/account/overview')
        ->assertSee('http://localhost/account/profile')
        ->assertSee('Keluar');
    $this->get('/en/account/overview')->assertOk()->assertSee('aria-current="page"', false)->assertSee('Profile');
});

it('adds, orders, hides and removes plugins\' pages in the menu', function () {
    Account::routes(function (string $suffix): void {
        Route::get('orders', fn () => view('account::layout'))->name('shop.orders'.$suffix);
        Route::get('points', fn () => 'points')->name('shop.points'.$suffix);
    });
    Account::page('orders', fn () => 'My orders', 'shop.orders', position: 10);
    Account::page('points', 'Points', 'shop.points', visible: fn () => false);
    Account::page('ghost', 'Ghost', 'shop.missing');

    $html = $this->get('/account/overview')->assertSee('My orders')->assertDontSee('Points')->assertDontSee('Ghost')->getContent();
    expect(strpos((string) $html, 'My orders'))->toBeLessThan(strpos((string) $html, 'Profil'));
    $this->get('/account/orders')->assertOk()->assertSee('aria-current="page"', false);

    Account::removePage('profile');
    $this->get('/account/overview')->assertDontSee('http://localhost/account/profile');
});

it('opens where a plugin says', function () {
    Account::landingUsing(fn ($user) => $user->email === 'ana@example.com' ? 'http://localhost/account/profile' : null);

    $this->get('/account')->assertRedirect('http://localhost/account/profile');
    $this->get('/account/overview')->assertOk();
});

it('shows plugins\' cards on the dashboard', function () {
    $dir = sys_get_temp_dir().'/account-cards-'.bin2hex(random_bytes(4));
    (new Filesystem)->ensureDirectoryExists("{$dir}/cards");
    file_put_contents("{$dir}/cards/points.blade.php", 'You have {{ $points }} points, {{ $user->name }}.');
    View::addLocation($dir);
    Account::dashboardCard('points', 'cards.points', fn ($user) => ['points' => 120]);

    try {
        $this->get('/account')->assertSee('You have 120 points, Ana.');
    } finally {
        (new Filesystem)->deleteDirectory($dir);
    }
});

it('shows the announcement as the first page, or above the pages', function () {
    accountSettings(['announcement' => true, 'announcementPosition' => 'tab', 'announcementTitle' => 'Libur', 'announcementTitles' => 'en: Holiday', 'announcementMessage' => "Toko tutup.\nSampai jumpa."]);

    $this->get('/account')->assertRedirect('http://localhost/account/announcement');
    $this->get('/account/announcement')->assertOk()->assertSee('Libur')->assertSee('Toko tutup.<br />', false);
    // In English, the main language's message.
    $this->get('/en/account/announcement')->assertSee('Holiday')->assertSee('Toko tutup.');

    accountSettings(['announcementPosition' => 'above']);
    $this->get('/account')->assertOk()->assertSee('account-announcement', false);
    $this->get('/account/announcement')->assertNotFound();

    accountSettings(['announcement' => false]);
    $this->get('/account')->assertDontSee('account-announcement', false);
});

it('escapes the announcement', function () {
    accountSettings(['announcement' => true, 'announcementPosition' => 'above', 'announcementMessage' => '<script>alert(1)</script>']);

    $this->get('/account')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});
