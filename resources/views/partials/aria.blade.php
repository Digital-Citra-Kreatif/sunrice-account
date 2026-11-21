{{-- Inside an input's tag: marks it invalid and points to its message while it has one. $name, $bag as partials.field-error. --}}
@error($name, $bag ?? 'default') aria-invalid="true" aria-describedby="account-error-{{ str_replace(['[', ']', '.'], '-', $name) }}" @enderror
