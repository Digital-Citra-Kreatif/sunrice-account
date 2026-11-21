{{--
    A field's validation message, tied to its input by id (partials.aria on the input). $name: the
    field; $bag: the form's error bag (default: 'default'). Not an alert of its own: the page's
    message summary announces that there are errors once.
--}}
@error($name, $bag ?? 'default')
    <span class="account-field__error" id="account-error-{{ str_replace(['[', ']', '.'], '-', $name) }}">{{ $message }}</span>
@enderror
