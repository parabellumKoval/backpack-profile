@php
    $rawValue = old($field['name']) ?? $field['value'] ?? $field['default'] ?? [];
    $currentValue = is_array($rawValue) ? $rawValue : [];
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    <div class="row">
        @foreach ($field['fields'] as $subfield)
            @php
                $subName = $subfield['name'];
                $inputName = $field['name'].'['.$subName.']';
                $dotName = square_brackets_to_dots($inputName);

                $value = old($dotName) ?? ($currentValue[$subName] ?? '');

                $wrapper = $subfield['wrapper'] ?? ['class' => 'form-group col-md-6'];
                $type = $subfield['type'] ?? 'text';

                $attributes = $subfield['attributes'] ?? [];
                $attributes['class'] = trim(($attributes['class'] ?? '').' form-control');
            @endphp
            <div @foreach($wrapper as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach>
                <label for="{{ $field['name'].'_'.$subName }}">{{ $subfield['label'] }}</label>
                <input
                    type="{{ $type }}"
                    name="{{ $inputName }}"
                    id="{{ $field['name'].'_'.$subName }}"
                    value="{{ $value }}"
                    @foreach($attributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach
                >
            </div>
        @endforeach
    </div>

    @if (!empty($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')
