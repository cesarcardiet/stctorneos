@php
    $inCategory = isset($category) && $category;
    $navTournament = $tournament ?? $category?->tournament;
    $activeNav = $active ?? '';
@endphp

<nav class="stc-app-nav" aria-label="Navegación rápida">
    @if ($inCategory)
        <a href="{{ route('workspace.categories.teams', $category) }}" @class(['is-active' => $activeNav === 'Equipos'])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 4.2v4.6c0 5.1-3.3 8.6-8 9.2-4.7-.6-8-4.1-8-9.2V7.2L12 3zm0 3.1L6.4 8.1v3.6c0 3.8 2.3 6.5 5.6 7.1 3.3-.6 5.6-3.3 5.6-7.1V8.1L12 6.1z"/></svg>
            <span>Equipos</span>
        </a>
        <a href="{{ route('workspace.categories.players', $category) }}" @class(['is-active' => $activeNav === 'Jugadores'])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-8 2-8 5v2h16v-2c0-3-4-5-8-5z"/></svg>
            <span>Jugadores</span>
        </a>
        <a href="{{ route('workspace.categories.standings', $category) }}" @class(['is-active' => $activeNav === 'Clasificación'])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V10h3.4v10H5zm5.3 0V4h3.4v16h-3.4zM15.6 20v-7H19v7h-3.4z"/></svg>
            <span>Tabla</span>
        </a>
        <a href="{{ route('workspace.categories.fixture', $category) }}" @class(['is-active' => $activeNav === 'Fixture'])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h2v2h6V3h2v2h3v16H4V5h3V3zm12 7H5v9h14v-9z"/></svg>
            <span>Fixture</span>
        </a>
    @elseif ($navTournament)
        <a href="{{ route('workspace.home') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11.4 12 4l8 7.4V20h-6v-5H10v5H4v-8.6z"/></svg>
            <span>Torneos</span>
        </a>
        <a href="{{ route('workspace.tournaments.show', $navTournament) }}" @class(['is-active' => $activeNav === 'Categorías'])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h7v7H4V5zm9 0h7v7h-7V5zM4 14h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>
            <span>Categorías</span>
        </a>
        <a href="{{ route('workspace.tournaments.clubs', $navTournament) }}" @class(['is-active' => $activeNav === 'Delegaciones'])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 4.2v4.6c0 5.1-3.3 8.6-8 9.2-4.7-.6-8-4.1-8-9.2V7.2L12 3z"/></svg>
            <span>Clubes</span>
        </a>
    @else
        <a href="{{ route('workspace.home') }}" @class(['is-active' => $activeNav === 'Torneos'])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11.4 12 4l8 7.4V20h-6v-5H10v5H4v-8.6z"/></svg>
            <span>Torneos</span>
        </a>
    @endif

    <button type="button" data-open-menu>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z"/></svg>
        <span>Menú</span>
    </button>
</nav>
