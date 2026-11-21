{{-- Choose a new password. $token, $email. --}}
@extends(\Sunrice\Account\View\Layout::site())
@include(\Sunrice\Account\View\Layout::view('partials.push-styles'))

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.reset.choose') }}</h1>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        {{-- The link no longer works (expired, used, or for another email): above the form, with a way to a new one. --}}
        @error('token')
            <p class="account-message account-message--error" role="alert">{{ $message }}
                <a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.password.request') }}">{{ __('account::auth.reset.new_link') }}</a></p>
        @enderror
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.password.update') }}" class="account-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="account-field"><span>{{ __('account::auth.fields.email') }}</span>
                <input type="email" name="email" @include(\Sunrice\Account\View\Layout::view('partials.aria'), ['name' => 'email']) value="{{ old('email', $email) }}" required autocomplete="email">
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
            </label>
            <label class="account-field"><span>{{ __('account::auth.fields.new_password') }}</span>
                <input type="password" name="password" @include(\Sunrice\Account\View\Layout::view('partials.aria'), ['name' => 'password']) required autocomplete="new-password" autofocus>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'password'])
            </label>
            <label class="account-field"><span>{{ __('account::auth.fields.password_confirmation') }}</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>
            <button type="submit" class="account-button account-button--primary">{{ __('account::auth.reset.submit') }}</button>
        </form>
        <p class="account-auth__alt">
            <a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.password.request') }}">{{ __('account::auth.reset.new_link') }}</a>
            · <a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.login') }}">{{ __('account::auth.reset.back') }}</a>
        </p>
    </section>
@endsection
