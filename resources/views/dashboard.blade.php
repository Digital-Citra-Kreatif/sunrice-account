@extends(\Sunrice\Account\View\Layout::view('layout'))

@section('account')
    <h1 class="account__title">{{ __('account::account.dashboard.title') }}</h1>
    <p class="account__greeting">{{ __('account::account.dashboard.greeting', ['name' => $user?->getAttribute('name')]) }}</p>
@endsection
