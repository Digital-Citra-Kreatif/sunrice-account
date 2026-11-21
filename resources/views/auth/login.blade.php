{{--
    Sign in with an email and a password. $captcha (the widget, empty when off), $registration.
--}}
@extends(\Sunrice\Account\View\Layout::site())

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.login.title') }}</h1>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.login.attempt') }}" class="account-form">
            @csrf <x-honeypot />
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
        @if ($registration)
            <p class="account-auth__alt">{{ __('account::auth.login.no_account') }}
                <a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.register') }}">{{ __('account::auth.login.register') }}</a></p>
        @endif
    </section>
@endsection
