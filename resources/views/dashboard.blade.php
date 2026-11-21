{{-- My Account's dashboard: a greeting, then the cards plugins add (Account::dashboardCard()). $user, $cards. --}}
@extends(\Sunrice\Account\View\Layout::view('layout'))

@section('account')
    <h1 class="account__title">{{ __('account::account.dashboard.title') }}</h1>
    <p class="account__greeting">{{ __('account::account.dashboard.greeting', ['name' => $user?->getAttribute('name')]) }}</p>
    @if ($cards !== [])
        <div class="account-cards">
            @foreach ($cards as $card)
                <div class="account-card account-card--{{ $card['key'] }}">@include($card['view'], $card['data'])</div>
            @endforeach
        </div>
    @endif
@endsection
