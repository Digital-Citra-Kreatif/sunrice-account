{{--
    Ask for a new email verification link. $captcha (the widget, empty when off); $email: a signed-in
    account's own address (the link goes there), else null (a guest enters theirs).
--}}
@extends(\Sunrice\Account\View\Layout::site())
@include(\Sunrice\Account\View\Layout::view('partials.push-styles'))

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.verify.title') }}</h1>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        @if ($email)
            <p>{{ __('account::auth.verify.pending', ['email' => $email]) }}</p>
        @else
            <p>{{ __('account::auth.verify.instructions') }}</p>
        @endif
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.verification.send') }}" class="account-form">
            @csrf <x-honeypot />
            @unless ($email)
                <label class="account-field"><span>{{ __('account::auth.fields.email') }}</span>
                    <input type="email" name="email" @include(\Sunrice\Account\View\Layout::view('partials.aria'), ['name' => 'email']) value="{{ old('email') }}" required autocomplete="email" autofocus>
                    @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
                </label>
            @else
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
            @endunless
            {{ $captcha }}
            <button type="submit" class="account-button account-button--primary">{{ $email ? __('account::auth.verify.resend') : __('account::auth.verify.send') }}</button>
        </form>
        @if ($email)
            <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.logout') }}" class="account-auth__alt">
                @csrf
                <button type="submit" class="account-link">{{ __('account::auth.logout') }}</button>
            </form>
        @else
            <p class="account-auth__alt"><a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.login') }}">{{ __('account::auth.reset.back') }}</a></p>
        @endif
    </section>
@endsection
