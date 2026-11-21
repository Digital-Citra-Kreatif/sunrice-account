{{--
    Create an account. $phone (off, optional, required), $marketing (offer the consent checkbox),
    $termsUrl (a terms page to agree to, or ''), $captcha (the widget, empty when off).
--}}
@extends(\Sunrice\Account\View\Layout::site())

@section('content')
    <section class="account-auth">
        <h1 class="account-auth__title">{{ __('account::auth.register.title') }}</h1>
        @include(\Sunrice\Account\View\Layout::view('partials.messages'))
        <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.register.store') }}" class="account-form">
            @csrf <x-honeypot />
            <label class="account-field"><span>{{ __('account::auth.fields.name') }}</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'name'])
            </label>
            <label class="account-field"><span>{{ __('account::auth.fields.email') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email">
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
            </label>
            @if ($phone !== 'off')
                <label class="account-field"><span>{{ __('account::auth.fields.phone') }}</span>
                    <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" @required($phone === 'required')>
                    @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'phone'])
                </label>
            @endif
            <label class="account-field"><span>{{ __('account::auth.fields.password') }}</span>
                <input type="password" name="password" required autocomplete="new-password">
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'password'])
            </label>
            <label class="account-field"><span>{{ __('account::auth.fields.password_confirmation') }}</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>
            @if ($marketing)
                <label class="account-check"><input type="checkbox" name="accepts_marketing" value="1" @checked(old('accepts_marketing'))> {{ __('account::auth.register.marketing') }}</label>
            @endif
            @if ($termsUrl !== '')
                <label class="account-check"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
                    {!! __('account::auth.register.terms', ['link' => '<a href="'.e($termsUrl).'" target="_blank" rel="noopener">'.e(__('account::auth.register.terms_name')).'</a>']) !!}</label>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'terms'])
            @endif
            {{ $captcha }}
            <button type="submit" class="account-button account-button--primary">{{ __('account::auth.register.submit') }}</button>
        </form>
        <p class="account-auth__alt">{{ __('account::auth.register.have_account') }}
            <a href="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.login') }}">{{ __('account::auth.register.login') }}</a></p>
    </section>
@endsection
