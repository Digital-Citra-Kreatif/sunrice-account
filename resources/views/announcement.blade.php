@extends(\Sunrice\Account\View\Layout::view('layout'))

@section('account')
    <h1 class="account__title">{{ $announcement->title() }}</h1>
    <div class="account-announcement__message">{!! nl2br(e($announcement->message())) !!}</div>
@endsection
