<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\View;
use Sunrice\Account\View\Layout;

it('uses the package\'s own site layout when the site has none', function () {
    expect(Layout::site())->toBe('account::layouts.site')
        ->and(Layout::view('dashboard'))->toBe('account::dashboard');
});

it('uses the app\'s copies of account views and its site layout', function () {
    $dir = sys_get_temp_dir().'/account-views-'.bin2hex(random_bytes(4));
    (new Filesystem)->ensureDirectoryExists("{$dir}/account");
    (new Filesystem)->ensureDirectoryExists("{$dir}/site");
    file_put_contents("{$dir}/account/dashboard.blade.php", "@extends(\\Sunrice\\Account\\View\\Layout::site())\n@section('content')Our own dashboard @endsection");
    file_put_contents("{$dir}/site/layout.blade.php", '<html><body class="our-site">@yield(\'content\')</body></html>');
    View::addLocation($dir);
    config(['sunrice-account.layout' => 'site.layout']);

    try {
        expect(Layout::view('dashboard'))->toBe('account.dashboard')
            ->and(Layout::site())->toBe('site.layout');

        $this->actingAs(siteUser(), 'account');
        $this->get('/account')->assertOk()->assertSee('our-site')->assertSee('Our own dashboard');
    } finally {
        (new Filesystem)->deleteDirectory($dir);
    }
});

it('styles the account pages in its own layout, with the site\'s nonce', function () {
    $this->get('/account/login')->assertOk()->assertSee('<style nonce=', false)->assertSee('.account-auth', false);
});

it('leaves the styles to the site\'s own layout', function () {
    $dir = sys_get_temp_dir().'/account-views-'.bin2hex(random_bytes(4));
    (new Filesystem)->ensureDirectoryExists("{$dir}/site");
    file_put_contents("{$dir}/site/layout.blade.php", '<html><body>@yield(\'content\')</body></html>');
    View::addLocation($dir);
    config(['sunrice-account.layout' => 'site.layout']);

    try {
        $this->get('/account/login')->assertOk()->assertDontSee('.account-auth {', false);
    } finally {
        (new Filesystem)->deleteDirectory($dir);
    }
});
