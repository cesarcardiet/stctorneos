@props([
    'position' => null,
    'label' => null,
    'size' => 'md',
])

@php
    [$zone, $dotY] = match ($position) {
        'Arquero' => ['gk', 84],
        'Defensor' => ['def', 68],
        'Mediocampista' => ['mid', 48],
        'Delantero' => ['fwd', 24],
        default => ['mid', 48],
    };
@endphp

<div @class(['pfc-pitch', 'is-'.$size]) aria-hidden="true">
    <svg viewBox="0 0 64 96" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="4" y="4" width="56" height="88" rx="6" stroke="currentColor" stroke-width="2"/>
        <line x1="4" y1="48" x2="60" y2="48" stroke="currentColor" stroke-width="1.5" opacity=".55"/>
        <circle cx="32" cy="48" r="8" stroke="currentColor" stroke-width="1.5" opacity=".55"/>
        <rect x="18" y="4" width="28" height="14" stroke="currentColor" stroke-width="1.5" opacity=".55"/>
        <rect x="18" y="78" width="28" height="14" stroke="currentColor" stroke-width="1.5" opacity=".55"/>
        <circle @class(['pfc-pitch-dot', 'is-'.$zone]) cx="32" cy="{{ $dotY }}" r="5" fill="currentColor"/>
    </svg>
    @if ($label)
        <span class="pfc-pitch-label">{{ $label }}</span>
    @endif
</div>
