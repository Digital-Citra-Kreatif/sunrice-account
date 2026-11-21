{{-- The announcement above or below account pages. $announcement. Its message is plain text, line breaks kept. --}}
<aside class="account-announcement" role="note">
    {{-- Not a heading: above the page it would come before the page's own h1. --}}
    <p class="account-announcement__title"><strong>{{ $announcement->title() }}</strong></p>
    <div class="account-announcement__message">{!! nl2br(e($announcement->message())) !!}</div>
</aside>
