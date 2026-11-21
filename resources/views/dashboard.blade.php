@extends(\Sunrice\Account\View\Layout::view('layout'))

@section('account')
    <h1 class="account__title">{{ __('account::account.dashboard.title') }}</h1>
    <p class="account__greeting">{{ __('account::account.dashboard.greeting', ['name' => $user?->getAttribute('name')]) }}</p>
    <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.logout') }}" class="account__signout">
        @csrf
        <button type="submit" class="account-button">{{ __('account::auth.logout') }}</button>
    </form>
@endsection
