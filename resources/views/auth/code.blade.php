{{-- Enter the emailed sign-in code. $email. --}}
@extends(\Sunrice\Account\View\Layout::site())

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.code.title') }}</h1>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.code.consume') }}" class="account-form">
            @csrf
            <label class="account-field"><span>{{ __('account::auth.code.label') }}</span>
                <input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="20" autofocus>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'code'])
            </label>
            <label class="account-check"><input type="checkbox" name="remember" value="1"> {{ __('account::auth.login.remember') }}</label>
            <button type="submit" class="account-button account-button--primary">{{ __('account::auth.code.submit') }}</button>
        </form>
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.code.send') }}" class="account-auth__resend">
            @csrf <x-honeypot />
            <input type="hidden" name="email" value="{{ $email }}">
            <button type="submit" class="account-link">{{ __('account::auth.code.resend') }}</button>
        </form>
        <p class="account-auth__alt"><a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.login') }}">{{ __('account::auth.reset.back') }}</a></p>
    </section>
@endsection
