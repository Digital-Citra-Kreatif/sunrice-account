{{--
    Sign in: with a password; with an emailed code ($codes: the first button, sending the same email, and the way in for a new address while $registration)
    and Google ($google) while they're on. $captcha (the widget, empty when off), $registration,
    $verifyEmail, $adminLogin (the admin's sign-in, while the site shares its guard; else null).
--}}
@extends(\Sunrice\Account\View\Layout::site())
@include(\Sunrice\Account\View\Layout::view('partials.push-styles'))

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.login.title') }}</h1>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        {{-- No honeypot here: password managers fill and send sign-in forms at once, and sign-in is throttled. --}}
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.login.attempt') }}" class="account-form">
            @csrf
            <label class="account-field"><span>{{ __('account::auth.fields.email') }}</span>
                <input type="email" name="email" @include(\Sunrice\Account\View\Layout::view('partials.aria'), ['name' => 'email']) value="{{ old('email') }}" required autocomplete="email" autofocus>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
                @if ($adminLogin && $errors->first('email') === __('account::auth.login.staff'))
                    <a class="account-auth__link" href="{{ $adminLogin }}">{{ __('account::auth.login.admin') }}</a>
                @endif
            </label>
            @if ($codes)
                {{-- The one-field way in, first: the same email, without a password. It makes a new address's account too. --}}
                @if ($registration)
                    <p class="account-auth__hint">{{ __('account::auth.login.code_hint') }}</p>
                @endif
                <button type="submit" class="account-button account-button--primary" formaction="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.code.send') }}" formnovalidate>{{ __('account::auth.login.with_code') }}</button>
                <p class="account-auth__or">{{ __('account::auth.login.or') }}</p>
            @endif
            <label class="account-field"><span>{{ __('account::auth.fields.password') }}</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="account-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> {{ __('account::auth.login.remember') }}</label>
            {{ $captcha }}
            <button type="submit" class="account-button {{ $codes ? '' : 'account-button--primary' }}">{{ __('account::auth.login.submit') }}</button>
            <a class="account-auth__link" href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.password.request') }}">{{ __('account::auth.login.forgot') }}</a>
            @if ($verifyEmail)
                <a class="account-auth__link" href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.verification.notice') }}">{{ __('account::auth.login.no_link') }}</a>
            @endif
        </form>
        @if ($google)
            <p class="account-auth__or">{{ __('account::auth.login.or') }}</p>
            <a class="account-button account-button--google" href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.google') }}">{{ __('account::auth.login.with_google') }}</a>
        @endif
        @if ($registration)
            <p class="account-auth__alt">{{ __('account::auth.login.no_account') }}
                <a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.register') }}">{{ __('account::auth.login.register') }}</a></p>
        @endif
    </section>
@endsection
