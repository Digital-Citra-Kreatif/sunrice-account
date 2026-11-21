{{--
    The signed-in account area: what every My Account page shares. Pages
    extend it and fill @section('account'). Other plugins' pages
    (Account::routes()) extend it the same way:

        @extends(\Sunrice\Account\View\Layout::view('layout'))
        @section('account') … @endsection
--}}
@extends(\Sunrice\Account\View\Layout::site())

@section('content')
    <section class="account">
        <div class="account__page">
            @include(\Sunrice\Account\View\Layout::view('partials.messages'))
            @yield('account')
        </div>
    </section>
@endsection
