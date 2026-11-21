{{-- My Account's menu. $items: key, label, url, route, icon. --}}
<nav class="account-nav" aria-label="{{ __('account::account.navigation') }}">
    <ul class="account-nav__list">
        @foreach ($items as $item)
            @php($active = request()->routeIs($item['route'], $item['route'].'.*'))
            <li class="account-nav__item account-nav__item--{{ $item['key'] }}">
                <a href="{{ $item['url'] }}" class="account-nav__link{{ $active ? ' is-active' : '' }}" @if ($active) aria-current="page" @endif>{{ $item['label'] }}</a>
            </li>
        @endforeach
    </ul>
    <form method="POST" action="{{ \Sunrice\Account\Routing\AccountRoutes::url('account.logout') }}" class="account-nav__signout">
        @csrf
        <button type="submit" class="account-link">{{ __('account::auth.logout') }}</button>
    </form>
</nav>
