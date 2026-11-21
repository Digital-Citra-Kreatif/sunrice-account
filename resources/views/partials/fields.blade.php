{{-- Fields plugins add (Account::registrationFields(), profileFields()). $fields; $values: the current values by key. --}}
@foreach ($fields as $field)
    @php($name = 'fields['.$field['key'].']')
    @php($value = old('fields.'.$field['key'], $values[$field['key']] ?? null))
    @if ($field['type'] === 'checkbox')
        <label class="account-check"><input type="checkbox" name="{{ $name }}" value="1" @checked((bool) $value)> {{ $field['label'] }}</label>
    @else
        <label class="account-field"><span>{{ $field['label'] }}</span>
            @if ($field['type'] === 'textarea')
                <textarea name="{{ $name }}" rows="3">{{ is_scalar($value) ? $value : '' }}</textarea>
            @elseif ($field['type'] === 'select')
                <select name="{{ $name }}">
                    <option value=""></option>
                    @foreach ($field['options'] as $option => $label)
                        <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <input type="text" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}">
            @endif
            @if ($field['help'] !== '')
                <small class="account-field__help">{{ $field['help'] }}</small>
            @endif
            @include(\Sunrice\Account\View\Layout::view('partials.field-error'), ['name' => 'fields.'.$field['key']])
        </label>
    @endif
@endforeach
