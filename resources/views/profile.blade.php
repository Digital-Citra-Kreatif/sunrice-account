{{--
    My Account → Profile. $user, $profile, $fields (plugins' profile fields), $phone (off, optional, required),
    $marketing (offer the consent checkbox), $deletion (offer asking for deletion).
--}}
@extends(\Sunrice\Account\View\Layout::view('layout'))

@section('account')
    <h1 class="account__title">{{ __('account::account.profile.title') }}</h1>

    <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.profile.update') }}" class="account-form account-form--profile">
        @csrf @method('PUT')
        <label class="account-field"><span>{{ __('account::auth.fields.name') }}</span>
            <input type="text" name="name" value="{{ old('name', $user->getAttribute('name')) }}" required maxlength="120" autocomplete="name">
            @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'name'])
        </label>
        <label class="account-field"><span>{{ __('account::auth.fields.email') }}</span>
            <input type="email" name="email" value="{{ old('email', $user->getAttribute('email')) }}" required maxlength="190" autocomplete="email">
            @if ($user->getAttribute('email_verified_at') === null)
                <small class="account-field__help">{{ __('account::account.profile.unverified') }}</small>
            @endif
            @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'email'])
        </label>
        @if ($profile->password_set)
            <label class="account-field"><span>{{ __('account::account.profile.current_password') }}</span>
                <input type="password" name="current_password" autocomplete="current-password">
                <small class="account-field__help">{{ __('account::account.profile.current_password_help') }}</small>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'current_password'])
            </label>
        @endif
        @if ($phone !== 'off')
            <label class="account-field"><span>{{ __('account::auth.fields.phone') }}</span>
                <input type="tel" name="phone" value="{{ old('phone', $profile->phone) }}" maxlength="30" autocomplete="tel" @required($phone === 'required')>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'phone'])
            </label>
        @endif
        @if ($marketing)
            <label class="account-check"><input type="checkbox" name="accepts_marketing" value="1" @checked(old('accepts_marketing', $profile->accepts_marketing))> {{ __('account::auth.register.marketing') }}</label>
        @endif
        @include(\Sunrice\Account\View\Layout::view('partials.fields'), ['fields' => $fields, 'values' => $profile->meta ?? []])
        <button type="submit" class="account-button account-button--primary">{{ __('account::account.profile.save') }}</button>
    </form>

    <h2 class="account__subtitle">{{ __($profile->password_set ? 'account::account.password.title' : 'account::account.password.set_title') }}</h2>
    <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.profile.password') }}" class="account-form account-form--password">
        @csrf @method('PUT')
        @if ($profile->password_set)
            <label class="account-field"><span>{{ __('account::account.profile.current_password') }}</span>
                <input type="password" name="current_password" required autocomplete="current-password">
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'current_password'])
            </label>
        @endif
        <label class="account-field"><span>{{ __('account::auth.fields.new_password') }}</span>
            <input type="password" name="password" required autocomplete="new-password">
            @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'password'])
        </label>
        <label class="account-field"><span>{{ __('account::auth.fields.password_confirmation') }}</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <button type="submit" class="account-button">{{ __('account::account.password.save') }}</button>
    </form>

    @if ($deletion)
        <h2 class="account__subtitle">{{ __('account::account.delete.title') }}</h2>
        @if ($profile->deletion_requested_at !== null)
            <p>{{ __('account::account.delete.pending', ['date' => $profile->deletion_requested_at->isoFormat('LL')]) }}</p>
        @else
            <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.profile.delete') }}" class="account-form account-form--delete">
                @csrf
                <p>{{ __('account::account.delete.intro') }}</p>
                <label class="account-check"><input type="checkbox" name="confirm" value="1" required> {{ __('account::account.delete.confirm') }}</label>
                @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'confirm'])
                <button type="submit" class="account-button account-button--danger">{{ __('account::account.delete.submit') }}</button>
            </form>
        @endif
    @endif
@endsection
