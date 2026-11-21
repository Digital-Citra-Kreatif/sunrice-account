{{-- The announcement above or below account pages. $announcement. Its message is plain text, line breaks kept. --}}
<aside class="account-announcement" role="note">
    <h2 class="account-announcement__title">{{ $announcement->title() }}</h2>
    <div class="account-announcement__message">{!! nl2br(e($announcement->message())) !!}</div>
</aside>
