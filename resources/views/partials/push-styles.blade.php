{{--
    The package's CSS for the account-* classes, pushed once to the site layout's `head` stack
    (the package's own layout and a theme's alike), unless config sunrice-account.styles is false
    (a theme that styles the account pages itself).
--}}
@if (config('sunrice-account.styles', true))
    @pushOnce('head', 'sunrice-account-styles')
        @include(\Sunrice\Account\View\Layout::view('partials.styles'))
    @endPushOnce
@endif
