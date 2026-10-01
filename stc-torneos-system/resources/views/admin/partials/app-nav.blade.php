<nav class="stc-app-nav" aria-label="Navegación rápida">
    <a href="{{ route('dashboard') }}" @class(['is-active' => ($active ?? 'Dashboard') === 'Dashboard'])>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11.4 12 4l8 7.4V20h-6v-5H10v5H4v-8.6z"/></svg>
        <span>Inicio</span>
    </a>
    @if (Route::has('admin.tournaments.index'))
        <a href="{{ route('admin.tournaments.index') }}" @class(['is-active' => ($active ?? '') === 'Torneos'])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h7v7H4V5zm9 0h7v7h-7V5zM4 14h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>
            <span>Torneos</span>
        </a>
    @endif
    @if (Route::has('admin.fixture.index'))
        <a href="{{ route('admin.fixture.index') }}" @class(['is-active' => ($active ?? '') === 'Fixture'])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h2v2h6V3h2v2h3v16H4V5h3V3zm12 7H5v9h14v-9z"/></svg>
            <span>Fixture</span>
        </a>
    @endif
    <a href="{{ route('admin.search') }}" @class(['is-active' => ($active ?? '') === 'Buscar'])>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 4a6.5 6.5 0 1 1 0 13 6.5 6.5 0 0 1 0-13zm0 2a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9zm7.2 11.1 3.2 3.2-1.4 1.4-3.2-3.2 1.4-1.4z"/></svg>
        <span>Buscar</span>
    </a>
    <button type="button" data-open-menu>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z"/></svg>
        <span>Menú</span>
    </button>
</nav>
