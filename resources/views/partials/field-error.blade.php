{{-- A field's validation message. $name: the field. --}}
@error($name)
    <span class="account-field__error" role="alert">{{ $message }}</span>
@enderror
