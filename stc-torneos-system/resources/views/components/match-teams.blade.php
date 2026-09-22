@props(['match'])

@php
    $home = $match?->homeTeam;
    $away = $match?->awayTeam;
    $homeSrc = $home?->shieldUrl() ?? asset('images/defaults/field.svg');
    $awaySrc = $away?->shieldUrl() ?? asset('images/defaults/field.svg');
@endphp

<span {{ $attributes->class(['entity-cell', 'entity-cell--match']) }}>
    <span class="sr-only">{{ $match?->title() }}</span>
    <span class="match-teams-line">
        <span class="match-teams-side">
            <img class="entity-thumb" src="{{ $homeSrc }}" alt="{{ $home?->name ?? 'Local' }}">
            @if ($home)
                <a class="row-title-link" href="{{ route('admin.teams.show', $home) }}">{{ $home->name }}</a>
            @else
                <strong>Local</strong>
            @endif
        </span>
        <span class="match-vs">vs</span>
        <span class="match-teams-side">
            <img class="entity-thumb" src="{{ $awaySrc }}" alt="{{ $away?->name ?? 'Visitante' }}">
            @if ($away)
                <a class="row-title-link" href="{{ route('admin.teams.show', $away) }}">{{ $away->name }}</a>
            @else
                <strong>Visitante</strong>
            @endif
        </span>
    </span>
    <small>
        @if ($match?->category)
            <a href="{{ route('admin.categories.show', $match->category) }}">{{ $match->category->name }}</a>
        @endif
        @if ($match?->round || $match?->stage)
            · {{ $match->round ?: $match->stage }}
        @endif
    </small>
</span>
