{{-- The page's status message (account.status) and a summary of form errors not shown by a field. --}}
@if (session('account.status'))
    <p class="account-message account-message--success" role="status">{{ session('account.status') }}</p>
@endif
@error('captcha')
    <p class="account-message account-message--error" role="alert">{{ $message }}</p>
@enderror
