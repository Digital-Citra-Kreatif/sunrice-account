{{-- Enter the emailed sign-in code. $email (where it went), $remember ("Keep me signed in" as ticked when it was asked for). --}}
@extends(\Sunrice\Account\View\Layout::site())
@include(\Sunrice\Account\View\Layout::view('partials.push-styles'))

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.code.title') }}</h1>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        <p>{{ __('account::auth.code.where', ['email' => $email]) }}</p>
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.code.consume') }}" class="account-form">
            @csrf
            <label class="account-field"><span>{{ __('account::auth.code.label') }}</span>
                <input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="20" autofocus>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'code'])
            </label>
            <label class="account-check"><input type="checkbox" name="remember" value="1" @checked(old('remember', $remember))> {{ __('account::auth.login.remember') }}</label>
            <button type="submit" class="account-button account-button--primary">{{ __('account::auth.code.submit') }}</button>
        </form>
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.code.send') }}" class="account-auth__resend">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            @if ($remember)
                <input type="hidden" name="remember" value="1">
            @endif
            <button type="submit" class="account-link">{{ __('account::auth.code.resend') }}</button>
            {{-- A new code asked for too soon. --}}
            @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
        </form>
        <p class="account-auth__alt"><a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.login') }}">{{ __('account::auth.reset.back') }}</a></p>
    </section>
@endsection
