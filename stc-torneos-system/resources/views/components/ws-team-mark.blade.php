@props([
    'team',
    'href' => null,
    'showCountry' => false,
    'layout' => 'row',
    'zone' => null,
])

@php
    $tag = $href ? 'a' : 'span';
    $isStack = $layout === 'stack';
@endphp

<{{ $tag }} {{ $attributes->class(['ws-team-mark', 'is-'.$layout])->merge($href ? ['href' => $href] : []) }}>
    <img class="ws-team-shield" src="{{ $team?->shieldUrl() ?? asset('images/stc-logo.png') }}" alt="{{ $team?->name ?? 'Equipo' }}">
    <span class="ws-team-copy">
        <span class="ws-team-title">
            @if (! $isStack)
                <x-ws-flag :team="$team" />
            @endif
            <strong>{{ strtoupper($team?->name ?? 'Equipo') }}</strong>
        </span>
        @if (! $isStack && $showCountry && $team?->countryName())
            <em class="ws-team-country-inline">{{ strtoupper($team->countryName()) }}</em>
        @endif
        @if ($isStack && $showCountry)
            <span class="ws-team-nation">
                <x-ws-flag :team="$team" />
                @if ($team?->countryName())
                    <em>{{ strtoupper($team->countryName()) }}</em>
                @endif
            </span>
        @endif
        @if ($zone)
            <span class="ws-team-zone">{{ $zone }}</span>
        @endif
    </span>
</{{ $tag }}>
