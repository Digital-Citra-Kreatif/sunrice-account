{{--
    Sign in: with a password; with an emailed code ($codes) and Google ($google) while they're on.
    $captcha (the widget, empty when off), $registration.
--}}
@extends(\Sunrice\Account\View\Layout::site())

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.login.title') }}</h1>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        {{-- No honeypot here: password managers fill and send sign-in forms at once, and sign-in is throttled. --}}
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.login.attempt') }}" class="account-form">
            @csrf
            <label class="account-field"><span>{{ __('account::auth.fields.email') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
            </label>
            <label class="account-field"><span>{{ __('account::auth.fields.password') }}</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="account-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> {{ __('account::auth.login.remember') }}</label>
            {{ $captcha }}
            <button type="submit" class="account-button account-button--primary">{{ __('account::auth.login.submit') }}</button>
            <a class="account-auth__link" href="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.password.request') }}">{{ __('account::auth.login.forgot') }}</a>
            <a class="account-auth__link" href="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.verification.notice') }}">{{ __('account::auth.login.no_link') }}</a>
        </form>
        @if ($codes || $google)
            <p class="account-auth__or">{{ __('account::auth.login.or') }}</p>
        @endif
        @if ($codes)
            <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.code.send') }}" class="account-form account-form--code">
                @csrf <x-honeypot />
                <label class="account-field"><span>{{ __('account::auth.fields.email') }}</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                </label>
                <button type="submit" class="account-button">{{ __('account::auth.login.with_code') }}</button>
            </form>
        @endif
        @if ($google)
            <a class="account-button account-button--google" href="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.google') }}">{{ __('account::auth.login.with_google') }}</a>
        @endif
        @if ($registration)
            <p class="account-auth__alt">{{ __('account::auth.login.no_account') }}
                <a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.register') }}">{{ __('account::auth.login.register') }}</a></p>
        @endif
    </section>
@endsection
