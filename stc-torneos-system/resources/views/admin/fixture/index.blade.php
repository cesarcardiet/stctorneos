<x-layouts.stc
    title="Fixture | STC Torneos"
    active="Fixture"
    heading="Fixture"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @php
        $baseFilters = array_filter([
            'search' => $filters['search'] ?: null,
            'status' => $filters['status'] !== 'all' ? $filters['status'] : null,
            'category_id' => $filters['category_id'] ?: null,
            'tournament_id' => $filters['tournament_id'] ?: null,
            'team_id' => $filters['team_id'] ?: null,
            'field_id' => $filters['field_id'] ?: null,
            'date' => $filters['date'] ?: null,
        ]);
    @endphp

    <section class="admin-figma-kpis">
        @foreach ($stats as [$value, $label, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <form class="category-filter-bar fixture-filter-bar player-filter-bar" method="get" action="{{ route('admin.fixture.index') }}">
        @if ($filters['round'] !== '')
            <input type="hidden" name="round" value="{{ $filters['round'] }}">
        @endif
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar equipo, cancha o fase">
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Estado: todos</option>
            @foreach (\App\Models\FixtureMatch::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="category_id">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="tournament_id">
            <option value="">Todos los torneos</option>
            @foreach ($accessibleTournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
            @endforeach
        </select>
        <select name="team_id">
            <option value="">Todos los equipos</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected((string) $filters['team_id'] === (string) $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
        <select name="field_id">
            <option value="">Todas las canchas</option>
            @foreach ($fields as $field)
                <option value="{{ $field->id }}" @selected((string) $filters['field_id'] === (string) $field->id)>{{ $field->name }} · {{ $field->venue?->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ $filters['date'] }}" min="1900-01-01" max="2100-12-31">
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.fixture.index') }}">Limpiar</a>
    </form>

    <div class="category-actions fixture-toolbar">
        <a href="{{ route('admin.fixture.generate') }}">Generar fixture</a>
        <a href="{{ route('admin.fixture.jornada', array_filter(['date' => $filters['date'] ?: now()->toDateString()])) }}">Mapa de la fecha</a>
        <a href="{{ route('admin.fixture.officials') }}">Árbitros y asistentes</a>
        <button type="submit" form="fixture-publish">Publicar en app</button>
    </div>

    <form id="fixture-publish" method="post" action="{{ route('admin.fixture.publish', request()->query()) }}">
        @csrf
        @method('PATCH')
    </form>

    <nav class="player-tabs fixture-round-tabs">
        <a href="{{ route('admin.fixture.index', $baseFilters) }}" @class(['active' => $filters['round'] === ''])>Todas</a>
        @foreach ($rounds as $round)
            <a href="{{ route('admin.fixture.index', $baseFilters + ['round' => $round]) }}" @class(['active' => $filters['round'] === $round])>{{ $round }}</a>
        @endforeach
    </nav>

    <section class="delegation-list-card admin-figma-panel">
        <header><h3>Agenda de partidos</h3></header>
        <div class="delegation-figma-table fixture-figma-table admin-figma-table">
            <div class="table-head">
                <span>Partido</span>
                <span>Fecha</span>
                <span>Cancha</span>
                <span>Estado</span>
                <span>Publicación</span>
                <span>Acción</span>
            </div>
            @forelse ($matches as $match)
                <div @class(['table-row', 'is-selected' => $match->status === 'live', 'has-conflict' => in_array($match->id, $conflictIds, true)])>
                    <span>
                        <x-match-teams :match="$match" />
                    </span>
                    <span>{{ $match->scheduled_at?->format('d M H:i') }}</span>
                    <span>{{ $match->field?->name }}</span>
                    <span class="status-text status-{{ $match->status }}">
                        {{ $match->statusLabel() }}
                        @if (in_array($match->id, $conflictIds, true))
                            <small>Conflicto</small>
                        @endif
                    </span>
                    <span>{{ $match->published ? 'En app' : 'Borrador' }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.fixture.show', $match) }}">Ver</a>
                        <a href="{{ route('admin.fixture.edit', $match) }}">Editar</a>
                        @if (in_array($match->id, $conflictIds, true))
                            <a href="{{ route('admin.fixture.edit', $match) }}">Resolver</a>
                        @endif
                    </span>
                </div>
            @empty
                <div class="category-empty-state"><strong>No hay partidos con esos filtros. Generá el fixture o programá un cruce.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
