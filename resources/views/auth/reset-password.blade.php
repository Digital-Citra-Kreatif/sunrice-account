{{-- Choose a new password. $token, $email. --}}
@extends(\Sunrice\Account\View\Layout::site())

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.reset.choose') }}</h1>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.password.update') }}" class="account-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="account-field"><span>{{ __('account::auth.fields.email') }}</span>
                <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
            </label>
            <label class="account-field"><span>{{ __('account::auth.fields.new_password') }}</span>
                <input type="password" name="password" required autocomplete="new-password" autofocus>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'password'])
            </label>
            <label class="account-field"><span>{{ __('account::auth.fields.password_confirmation') }}</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>
            <button type="submit" class="account-button account-button--primary">{{ __('account::auth.reset.submit') }}</button>
        </form>
    </section>
@endsection
