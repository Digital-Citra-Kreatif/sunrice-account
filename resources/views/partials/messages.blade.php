{{-- The page's status message (account.status), a problem to show (account.error), and form errors not shown by a field. --}}
@if (session('account.status'))
    <p class="account-message account-message--success" role="status">{{ session('account.status') }}</p>
@endif
@if (session('account.error'))
    <p class="account-message account-message--error" role="alert">{{ session('account.error') }}</p>
@endif
@if (collect($errors->getBags())->contains(fn ($bag) => $bag->any()))
    {{-- One announcement for the form's errors; each field's message is tied to its input. --}}
    <p class="account-message account-message--error" role="alert">{{ $errors->first('captcha') ?: __('account::auth.check_fields') }}</p>
@endif
