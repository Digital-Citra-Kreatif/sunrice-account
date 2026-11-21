{{--
    The account emails' markdown template: Laravel's notification email, with the site's brand
    (Panel::mailBrand(): its logo, else its name) at the top and its name in the footer.
    An app replaces one email's body with resources/views/account/emails/{key}.blade.php.
--}}
@php($accountBrand = app(\Sunrice\Admin\Panel::class)->mailBrand())
<x-mail::layout>
<x-slot:header>
<tr>
<td class="header">
<a href="{{ config('app.url') }}" style="display: inline-block;">
@if ($accountBrand->logo !== null)
<img src="{{ $accountBrand->logo }}" alt="{{ $accountBrand->name }}" width="{{ $accountBrand->width }}" height="{{ $accountBrand->height }}" style="display: block; border: 0; outline: none; text-decoration: none; width: {{ $accountBrand->width }}px; height: {{ $accountBrand->height }}px; max-width: 100%; max-height: {{ \Sunrice\Admin\Mail\MailBrand::HEIGHT }}px;">
@else
{{ $accountBrand->name }}
@endif
</a>
</td>
</tr>
</x-slot:header>

@if (! empty($greeting))
# {{ $greeting }}
@endif

@foreach ($introLines as $line)
{{ $line }}

@endforeach

@isset($actionText)
<x-mail::button :url="$actionUrl" color="primary">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach

@if (! empty($salutation))
{{ $salutation }}
@else
{{ $accountBrand->name }}
@endif

@isset($actionText)
<x-slot:subcopy>
<x-mail::subcopy>
{{ __('account::emails.trouble', ['action' => $actionText], $lang ?? null) }} <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $accountBrand->name }}.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
