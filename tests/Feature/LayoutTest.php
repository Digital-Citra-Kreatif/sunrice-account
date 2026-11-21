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
    foreach (['vendor/account', 'account', 'site'] as $folder) {
        (new Filesystem)->ensureDirectoryExists("{$dir}/{$folder}");
    }
    file_put_contents("{$dir}/vendor/account/dashboard.blade.php", "@extends(\\Sunrice\\Account\\View\\Layout::site())\n@section('content')Our own dashboard @endsection");
    // The app's own account/ views are its own business: never taken for the package's.
    file_put_contents("{$dir}/account/dashboard.blade.php", 'Not for the account package');
    file_put_contents("{$dir}/site/layout.blade.php", '<html><body class="our-site">@yield(\'content\')</body></html>');
    View::addLocation($dir);
    // As Laravel does for resources/views/vendor/account/.
    View::prependNamespace('account', "{$dir}/vendor/account");
    config(['sunrice-account.layout' => 'site.layout']);

    try {
        expect(Layout::view('dashboard'))->toBe('account::dashboard')
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

it('pushes its styles to a theme\'s layout, unless the theme styles the pages itself', function () {
    $dir = sys_get_temp_dir().'/account-views-'.bin2hex(random_bytes(4));
    (new Filesystem)->ensureDirectoryExists("{$dir}/site");
    file_put_contents("{$dir}/site/layout.blade.php", '<html><head>@stack(\'head\')</head><body>@yield(\'content\')</body></html>');
    View::addLocation($dir);
    config(['sunrice-account.layout' => 'site.layout']);

    try {
        $html = (string) $this->get('/account/login')->assertOk()->getContent();
        expect(substr_count($html, '<style nonce='))->toBe(1)->and($html)->toContain('.account-auth {');

        config(['sunrice-account.styles' => false]);
        $this->get('/account/login')->assertOk()->assertDontSee('.account-auth {', false);
    } finally {
        (new Filesystem)->deleteDirectory($dir);
    }
});
