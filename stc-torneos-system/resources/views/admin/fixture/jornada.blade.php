<x-layouts.stc
    title="Jornada | STC Torneos"
    active="Fixture"
    heading="Mapa de la fecha"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.fixture.index') }}">← Volver a la agenda</a>
    <div class="category-actions fixture-toolbar">
        <a href="{{ route('admin.fixture.officials') }}">Árbitros y asistentes</a>
    </div>

    <form class="category-filter-bar fixture-filter-bar player-filter-bar" method="get" action="{{ route('admin.fixture.jornada') }}">
        <input type="date" name="date" value="{{ $date }}" min="1900-01-01" max="2100-12-31">
        <select name="tournament_id">
            <option value="">Todos los torneos</option>
            @foreach ($accessibleTournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
            @endforeach
        </select>
        <select name="category_id">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="field_id">
            <option value="">Todas las canchas</option>
            @foreach ($fields as $field)
                <option value="{{ $field->id }}" @selected((string) $filters['field_id'] === (string) $field->id)>{{ $field->name }}</option>
            @endforeach
        </select>
        <button type="submit">Ver jornada</button>
    </form>

    @if ($conflicts->isNotEmpty())
        <div class="system-alert">Hay {{ $conflicts->count() }} conflicto{{ $conflicts->count() === 1 ? '' : 's' }} de cancha, horario o árbitro.</div>
    @endif

    <div class="standings-stack results-standings">
        @forelse ($byField as $fieldName => $rows)
            <article class="standings-card">
                <header>
                    <span>{{ strtoupper($fieldName) }}</span>
                    <small>{{ $rows->count() }} partido{{ $rows->count() === 1 ? '' : 's' }}</small>
                </header>
                <div class="standings-table">
                    <div class="table-head">
                        <span>Hora</span><span>Partido</span><span>Árbitro</span><span>Asistente</span><span>Estado</span><span></span>
                    </div>
                    @foreach ($rows as $match)
                        <div class="table-row">
                            <span>{{ $match->scheduled_at?->format('H:i') }}</span>
                            <span>
                                <strong>{{ $match->title() }}</strong>
                                <small>{{ $match->category?->name }}@if(in_array($match->id, $conflictIds, true)) · conflicto @endif</small>
                            </span>
                            <span>{{ $match->refereeLabel() }}</span>
                            <span>{{ $match->assistantLabel() }}</span>
                            <span>{{ $match->statusLabel() }}</span>
                            <span><a href="{{ route('admin.fixture.show', $match) }}">Abrir</a></span>
                        </div>
                    @endforeach
                </div>
            </article>
        @empty
            <div class="category-empty-state"><strong>No hay partidos programados para esa fecha.</strong></div>
        @endforelse
    </div>
</x-layouts.stc>
