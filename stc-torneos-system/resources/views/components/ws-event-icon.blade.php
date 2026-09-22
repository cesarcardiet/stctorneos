@props([
    'type' => 'goal',
    'size' => 'md',
])

@php
    $type = in_array($type, ['goal', 'yellow', 'red', 'assist', 'substitution'], true) ? $type : 'goal';
@endphp

<span {{ $attributes->class(['ws-play-icon', 'is-'.$type, 'is-'.$size]) }} aria-hidden="true">
    @if ($type === 'goal')
        <svg viewBox="0 0 64 64">
            <path d="M6 18c8 0 14 6 16 10" fill="none" stroke="#22c55e" stroke-width="3.2" stroke-linecap="round"/>
            <path d="M4 28c9 0 14 5 16 9" fill="none" stroke="#4ade80" stroke-width="2.6" stroke-linecap="round"/>
            <path d="M8 38c7 1 12 5 14 8" fill="none" stroke="#22c55e" stroke-width="2.4" stroke-linecap="round"/>
            <circle cx="38" cy="34" r="18" fill="#151515"/>
            <circle cx="38" cy="34" r="18" fill="none" stroke="#f4f4f4" stroke-width="2"/>
            <polygon points="38,21 45,26 42,35 34,35 31,26" fill="#f7f7f7"/>
            <path d="M38 21 31 26 24 23" fill="none" stroke="#f7f7f7" stroke-width="2.1" stroke-linejoin="round"/>
            <path d="M45 26 51 24 52 32" fill="none" stroke="#f7f7f7" stroke-width="2.1" stroke-linejoin="round"/>
            <path d="M42 35 47 43 39 48" fill="none" stroke="#f7f7f7" stroke-width="2.1" stroke-linejoin="round"/>
            <path d="M34 35 28 44 22 38" fill="none" stroke="#f7f7f7" stroke-width="2.1" stroke-linejoin="round"/>
            <path d="M31 26 22 30 24 23" fill="none" stroke="#f7f7f7" stroke-width="2.1" stroke-linejoin="round"/>
        </svg>
    @elseif ($type === 'yellow')
        <svg viewBox="0 0 64 64">
            <g transform="rotate(-16 32 32)">
                <rect x="20" y="10" width="24" height="38" rx="5" fill="#f3c200"/>
                <rect x="20" y="10" width="24" height="38" rx="5" fill="none" stroke="#c99700" stroke-width="2"/>
                <rect x="24" y="16" width="16" height="4" rx="2" fill="rgba(255,255,255,.45)"/>
            </g>
        </svg>
    @elseif ($type === 'red')
        <svg viewBox="0 0 64 64">
            <g transform="rotate(14 32 32)">
                <rect x="20" y="10" width="24" height="38" rx="5" fill="#e23b3b"/>
                <rect x="20" y="10" width="24" height="38" rx="5" fill="none" stroke="#a51f1f" stroke-width="2"/>
                <rect x="24" y="16" width="16" height="4" rx="2" fill="rgba(255,255,255,.35)"/>
            </g>
        </svg>
    @elseif ($type === 'assist')
        <svg viewBox="0 0 64 64">
            <circle cx="22" cy="40" r="12" fill="#151515"/>
            <circle cx="22" cy="40" r="12" fill="none" stroke="#f4f4f4" stroke-width="2"/>
            <polygon points="22,32 27,36 25,42 19,42 17,36" fill="#f7f7f7"/>
            <path d="M32 28c8-8 16-8 24-2" fill="none" stroke="#00d5ff" stroke-width="3.2" stroke-linecap="round"/>
            <path d="M50 20 58 26 49 30" fill="none" stroke="#00d5ff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @else
        <svg viewBox="0 0 64 64">
            <path d="M14 22h20l-8 10H14z" fill="#00d5ff"/>
            <path d="M50 42H30l8-10h12z" fill="#f3c200"/>
            <path d="M18 18 10 26 18 34" fill="none" stroke="#00d5ff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M46 46 54 38 46 30" fill="none" stroke="#f3c200" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @endif
</span>
