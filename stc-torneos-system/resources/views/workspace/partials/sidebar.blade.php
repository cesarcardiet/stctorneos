@php
    $navUser = auth()->user();
    $navUser?->loadMissing(['roles', 'tournament']);
    $inCategory = isset($category) && $category;
    $navTournament = $tournament ?? $category?->tournament;
    $navLevel = $inCategory ? 'category' : ($navTournament ? 'tournament' : 'gate');
@endphp

<aside class="stc-sidebar" id="stc-sidebar">
    <div class="stc-sidebar-head">
        <a href="{{ route('workspace.home') }}" class="stc-brand" aria-label="STC Operación">
            <img class="stc-brand-logo" src="{{ asset('images/stc-logo.png') }}" alt="STC Torneos">
            <small>Operación</small>
        </a>
        <button type="button" class="stc-icon-btn stc-sidebar-close" data-close-menu aria-label="Cerrar menú">✕</button>
    </div>

    @if ($navUser)
        <div class="stc-user-card">
            <strong>{{ $navUser->name }}</strong>
            <span>{{ $navUser->roleLabel() }}</span>
            @if ($inCategory)
                <small><a href="{{ route('workspace.tournaments.show', $category->tournament) }}">{{ $category->tournament->name }}</a> · {{ $category->name }}</small>
            @elseif ($navTournament)
                <small>{{ $navTournament->name }}</small>
            @else
                <small>Elegí un torneo</small>
            @endif
        </div>
    @endif

    <nav class="stc-nav ws-nav">
        @if ($navLevel === 'gate')
            <a href="{{ route('workspace.home') }}" @class(['active' => ($active ?? '') === 'Torneos'])>
                <span><strong>Torneos</strong><small>Elegí uno</small></span><b>›</b>
            </a>
        @elseif ($navLevel === 'tournament')
            <a href="{{ route('workspace.home') }}">
                <span><strong>← Torneos</strong><small>Volver al listado</small></span><b>‹</b>
            </a>
            <div class="ws-nav-context">
                <small>Torneo</small>
                <strong>{{ $navTournament->name }}</strong>
            </div>
            <a href="{{ route('workspace.tournaments.show', $navTournament) }}" @class(['active' => ($active ?? '') === 'Categorías'])>
                <span><strong>Categorías</strong><small>Elegí una</small></span><b>›</b>
            </a>
            <a href="{{ route('workspace.tournaments.clubs', $navTournament) }}" @class(['active' => ($active ?? '') === 'Delegaciones'])>
                <span><strong>Delegaciones</strong><small>Clubes</small></span><b>›</b>
            </a>
        @else
            <a href="{{ route('workspace.home') }}">
                <span><strong>Torneos</strong><small>Listado</small></span><b>›</b>
            </a>
            <a href="{{ route('workspace.tournaments.show', $category->tournament) }}">
                <span><strong>Categorías</strong><small>{{ $category->tournament->name }}</small></span><b>›</b>
            </a>
            <div class="ws-nav-context">
                <small>Categoría</small>
                <a class="ws-nav-context-link" href="{{ route('workspace.categories.teams', $category) }}">{{ $category->name }}</a>
            </div>
            <a href="{{ route('workspace.categories.teams', $category) }}" @class(['active' => ($active ?? '') === 'Equipos'])>
                <span><strong>Equipos</strong><small>Planteles</small></span><b>›</b>
            </a>
            <a href="{{ route('workspace.categories.players', $category) }}" @class(['active' => ($active ?? '') === 'Jugadores'])>
                <span><strong>Jugadores</strong><small>Fichas</small></span><b>›</b>
            </a>
            <p class="ws-nav-section">Operar</p>
            <a href="{{ route('workspace.categories.standings', $category) }}" @class(['active' => ($active ?? '') === 'Clasificación'])>
                <span><strong>Clasificación</strong><small>Tablas</small></span><b>›</b>
            </a>
            <a href="{{ route('workspace.categories.fixture', $category) }}" @class(['active' => ($active ?? '') === 'Fixture'])>
                <span><strong>Fixture</strong><small>Partidos</small></span><b>›</b>
            </a>
            <a href="{{ route('workspace.categories.settings', $category) }}" @class(['active' => in_array(($active ?? ''), ['Configuración', 'Media', 'Rankings'], true)])>
                <span><strong>Configuración</strong><small>Ajustes</small></span><b>›</b>
            </a>
        @endif
    </nav>
    <a class="stc-logout" href="{{ route('logout.demo') }}">Salir</a>
</aside>
