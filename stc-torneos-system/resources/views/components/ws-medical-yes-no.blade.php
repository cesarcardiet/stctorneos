@props([
    'name',
    'detailName',
    'label',
    'detailLabel' => 'Detalle del tratamiento',
    'value' => null,
    'detail' => '',
    'disabled' => false,
    'detailPlaceholder' => 'Indicá qué tratamiento está en curso',
    'detailClass' => 'ws-span-2',
])

@php
    $selected = old($name, $value === null ? '' : ($value ? '1' : '0'));
    $detailValue = old($detailName, $detail);
@endphp

<label {{ $attributes->merge(['class' => 'ws-yes-no-field']) }} data-ws-yes-no>
    <span>{{ $label }}</span>
    <select name="{{ $name }}" data-ws-yes-no-select @disabled($disabled)>
        <option value="" @selected($selected === '')>Sin cargar</option>
        <option value="1" @selected($selected === '1')>Sí</option>
        <option value="0" @selected($selected === '0')>No</option>
    </select>
</label>
<label class="{{ $detailClass }} ws-yes-no-detail" data-ws-yes-no-detail data-ws-yes-no-for="{{ $name }}" @if($selected !== '1') hidden @endif>
    <span>{{ $detailLabel }}</span>
    <textarea
        name="{{ $detailName }}"
        rows="2"
        placeholder="{{ $detailPlaceholder }}"
        @disabled($disabled)
    >{{ $detailValue }}</textarea>
</label>
