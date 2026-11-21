{{-- Fields plugins add (Account::registrationFields(), profileFields()). $fields; $values: the current values by key. --}}
{{-- After an error, what was sent (a checkbox left unticked isn't sent at all); else the saved values. --}}
@php($submitted = session()->getOldInput('name') !== null || session()->getOldInput('email') !== null)
@foreach ($fields as $field)
    @php($name = 'fields['.$field['key'].']')
    @php($errorName = 'fields.'.$field['key'])
    @php($value = $submitted ? old('fields.'.$field['key']) : ($values[$field['key']] ?? null))
    @if ($field['type'] === 'checkbox')
        <div class="account-field account-field--check">
            <label class="account-check"><input type="checkbox" name="{{ $name }}" value="1" @checked((bool) $value) @include(\Sunrice\Account\View\Layout::view('partials.aria'), ['name' => $errorName])> {{ $field['label'] }}</label>
            @if ($field['help'] !== '')
                <small class="account-field__help">{{ $field['help'] }}</small>
            @endif
            @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => $errorName])
        </div>
    @else
        <label class="account-field"><span>{{ $field['label'] }}</span>
            @if ($field['type'] === 'textarea')
                <textarea name="{{ $name }}" rows="3" @include(\Sunrice\Account\View\Layout::view('partials.aria'), ['name' => $errorName])>{{ is_scalar($value) ? $value : '' }}</textarea>
            @elseif ($field['type'] === 'select')
                <select name="{{ $name }}" @include(\Sunrice\Account\View\Layout::view('partials.aria'), ['name' => $errorName])>
                    <option value="">{{ __('account::auth.fields.choose') }}</option>
                    @foreach ($field['options'] as $option => $label)
                        <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <input type="text" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}" @include(\Sunrice\Account\View\Layout::view('partials.aria'), ['name' => $errorName])>
            @endif
            @if ($field['help'] !== '')
                <small class="account-field__help">{{ $field['help'] }}</small>
            @endif
            @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => $errorName])
        </label>
    @endif
@endforeach
