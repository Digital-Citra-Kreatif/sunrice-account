{{--
    The minimal site layout account pages use when the site has none
    (sunrice-account.layout, by default the CMS's cms.layout).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \Sunrice\Cms\Support\Locales::dir() }}" class="account-site">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-sunrice-cms::seo :default-title="$seoTitle ?? null" :noindex="(bool) request()->attributes->get(\Sunrice\Account\Http\Middleware\NoIndex::ATTRIBUTE)" />
    @stack('head')
    <x-sunrice-cms::code place="head" />
</head>
<body @bodyClass('account-body')>
    <x-sunrice-cms::code place="body-start" />
    <main class="account-main">
        @yield('content')
    </main>
    @stack('scripts')
    <x-sunrice-cms::code place="body-end" />
</body>
</html>
