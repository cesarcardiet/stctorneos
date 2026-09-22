@props([
    'name' => 'shield_file',
    'src' => null,
    'compact' => false,
    'saveUrl' => null,
    'heading' => 'Escudo del club',
    'hint' => 'Vista previa · este escudo es del club y se usa en todos los equipos de la delegación.',
])

@php
    $preview = $src ?: asset('images/stc-logo.png');
@endphp

<div
    {{ $attributes->class(['ws-shield-upload', 'is-compact' => $compact]) }}
    data-ws-shield-crop
    @if ($saveUrl) data-ws-shield-save="{{ $saveUrl }}" @endif
>
    <span>{{ $heading }}</span>
    <button type="button" class="ws-shield-preview-wrap" data-ws-shield-pick title="Elegir imagen">
        <img data-ws-shield-preview src="{{ $preview }}" alt="Vista previa del escudo">
    </button>
    <div class="ws-shield-actions">
        <label class="ws-btn ghost ws-shield-pick-label">
            Cambiar
            <input class="ws-file-hidden" type="file" name="{{ $name }}" accept="image/png,image/jpeg,image/webp,image/gif,image/heic,image/heif">
        </label>
        <button type="button" class="ws-btn ghost" data-ws-shield-adjust>Ajustar</button>
    </div>
    <input type="hidden" name="shield_data" value="" data-ws-shield-data>
    <small>{{ $hint }}</small>
</div>
