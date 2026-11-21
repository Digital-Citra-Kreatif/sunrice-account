{{-- Ask for a password reset link. $captcha (the widget, empty when off). --}}
@extends(\Sunrice\Account\View\Layout::site())
@include(\Sunrice\Account\View\Layout::view('partials.push-styles'))

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.reset.title') }}</h1>
        <p>{{ __('account::auth.reset.instructions') }}</p>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.password.email') }}" class="account-form">
            @csrf <x-honeypot />
            <label class="account-field"><span>{{ __('account::auth.fields.email') }}</span>
                <input type="email" name="email" @include(\Sunrice\Account\View\Layout::view('partials.aria'), ['name' => 'email']) value="{{ old('email') }}" required autocomplete="email" autofocus>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
            </label>
            {{ $captcha }}
            <button type="submit" class="account-button account-button--primary">{{ __('account::auth.reset.send') }}</button>
        </form>
        <p class="account-auth__alt"><a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('sunrice.account.login') }}">{{ __('account::auth.reset.back') }}</a></p>
    </section>
@endsection
