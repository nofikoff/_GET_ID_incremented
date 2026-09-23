{{-- $name, $label, $value; optional $type ('text' by default, or 'textarea') and $required. --}}
<p>
    <label>{{ $label }}<br>
        @if (($type ?? 'text') === 'textarea')
            <textarea name="{{ $name }}" rows="3">{{ $value ?? '' }}</textarea>
        @else
            <input type="{{ $type ?? 'text' }}" name="{{ $name }}" value="{{ $value ?? '' }}" @required($required ?? false)>
        @endif
    </label>
    @include('admin.partials.error', ['field' => $name])
</p>
