@props([
    'label',
    'span' => 1,
    'hint' => null,
])

<div @class(['ws-field', 'ws-span-2' => (int) $span === 2])>
    <span class="ws-field-label">{{ $label }}</span>
    {{ $slot }}
    @if ($hint)
        <small class="ws-field-hint">{{ $hint }}</small>
    @endif
</div>
