{{-- A field's validation message. $name: the field; $bag: the form's error bag (default: 'default'). --}}
@error($name, $bag ?? 'default')
    <span class="account-field__error" role="alert">{{ $message }}</span>
@enderror
