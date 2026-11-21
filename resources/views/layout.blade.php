{{--
    The signed-in account area: the account menu beside the page, the
    announcement (above or below) and the status messages. Pages extend it
    and fill @section('account'). Other plugins' pages (Account::routes())
    extend it the same way:

        @extends(\Sunrice\Account\View\Layout::view('layout'))
        @section('account') … @endsection

    The current menu item is the one whose route matches the page's.
--}}
@extends(\Sunrice\Account\View\Layout::site())
@include(\Sunrice\Account\View\Layout::view('partials.push-styles'))
@php($accountNavigation = app(\Sunrice\Account\MyAccount\Navigation::class)->items())
@php($accountAnnouncement = app(\Sunrice\Account\MyAccount\Announcement::class))
@php($accountAnnouncementPosition = $accountAnnouncement->position())

@section('content')
    <section class="account">
        @include(\Sunrice\Account\View\Layout::view('partials.navigation'), ['items' => $accountNavigation])
        <div class="account__page">
            @include(\Sunrice\Account\View\Layout::view('partials.messages'))
            @if ($accountAnnouncementPosition === 'above')
                @include(\Sunrice\Account\View\Layout::view('partials.announcement-banner'), ['announcement' => $accountAnnouncement])
            @endif
            @yield('account')
            @if ($accountAnnouncementPosition === 'below')
                @include(\Sunrice\Account\View\Layout::view('partials.announcement-banner'), ['announcement' => $accountAnnouncement])
            @endif
        </div>
    </section>
@endsection
