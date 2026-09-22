<x-layouts.stc
    title="Torneos | STC Torneos"
    active="Torneos"
    heading="Listado de Torneos"
    subheading="Control de eventos, estados, sedes e inscripciones"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <form class="category-filter-bar tournament-filter-form" method="get" action="{{ route('admin.tournaments.index') }}">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar por nombre">
        <select name="status">
            <option value="all">Estado: todos</option>
            @foreach (\App\Models\Tournament::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input name="venue" value="{{ $filters['venue'] }}" placeholder="Sede o ciudad">
        <input type="date" name="period_from" value="{{ $filters['period_from'] }}" title="Período desde" min="1900-01-01" max="2100-12-31">
        <input type="date" name="period_to" value="{{ $filters['period_to'] }}" title="Período hasta" min="1900-01-01" max="2100-12-31">
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.tournaments.index') }}">Limpiar</a>
    </form>

    <section class="figma-filter-bar tournament-status-pills">
        @foreach ($filterOptions as $value => $label)
            <a @class(['active' => $filter === $value]) href="{{ route('admin.tournaments.index', array_filter(['filter' => $value, 'search' => $filters['search'], 'status' => $filters['status'] !== 'all' ? $filters['status'] : null, 'venue' => $filters['venue'] ?: null, 'period_from' => $filters['period_from'] ?: null, 'period_to' => $filters['period_to'] ?: null])) }}">{{ $label }}</a>
        @endforeach
    </section>

    <section class="figma-tournament-grid">
        <article class="stc-card table-card figma-table-card admin-figma-panel">
            <div class="stc-table tournament-table admin-figma-table">
                <div class="table-head">
                    <span>Torneo</span>
                    <span>Fecha</span>
                    <span>Sede</span>
                    <span>Estado</span>
                    <span>Cat.</span>
                    <span>Equipos</span>
                    <span>Acciones</span>
                </div>

                @forelse ($tournaments as $tournament)
                    <div class="table-row tournament-list-row">
                        <span>
                            <x-entity-cell
                                :href="route('admin.tournaments.show', $tournament)"
                                :src="$tournament->logoUrl()"
                                :alt="$tournament->name"
                            >
                                {{ $tournament->name }}
                                <x-slot:subtitle>{{ $tournament->edition }}</x-slot:subtitle>
                            </x-entity-cell>
                        </span>
                        <span>
                            {{ $tournament->starts_at->format('d/m/Y') }}
                            <small>al {{ $tournament->ends_at->format('d/m/Y') }}</small>
                        </span>
                        <span>
                            {{ $tournament->city ?: $tournament->location }}
                            <small>{{ $tournament->venue_name }}</small>
                        </span>
                        <span>{{ $tournament->statusLabel() }}</span>
                        <span>{{ $tournament->categories_count }}</span>
                        <span>{{ $tournament->teams_count }}</span>
                        <span class="teams-cell">
                            <a href="{{ route('admin.tournaments.show', $tournament) }}">Ver</a>
                            <a href="{{ route('admin.tournaments.edit', $tournament) }}">Editar</a>
                            @if ($canCreateTournaments)
                                <form method="post" action="{{ route('admin.tournaments.duplicate', $tournament) }}">
                                    @csrf
                                    <button class="ghost-action" type="submit">Duplicar</button>
                                </form>
                                <form method="post" action="{{ route('admin.tournaments.destroy', $tournament) }}" data-confirm="¿Eliminar {{ $tournament->name }}? Se borran categorías, equipos y partidos.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ghost-action" type="submit">Eliminar</button>
                                </form>
                            @endif
                        </span>
                    </div>
                @empty
                    <div class="table-row tournament-list-row">
                        <span>
                            <strong>Sin torneos para este filtro</strong>
                            <small>Cambiá filtros o creá un torneo si sos Admin General.</small>
                        </span>
                        <span>-</span>
                        <span>-</span>
                        <span>-</span>
                        <span>-</span>
                        <span>-</span>
                        <span class="teams-cell">
                            @if ($canCreateTournaments)
                                <a href="{{ route('admin.tournaments.create') }}">Crear</a>
                            @endif
                        </span>
                    </div>
                @endforelse
            </div>
        </article>

        <aside class="stc-card figma-summary-card">
            <h3>Resumen</h3>
            <div class="figma-summary-list">
                @foreach ($summary as [$label, $value])
                    <div>
                        <strong>{{ $value }}</strong>
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>
            @if ($canCreateTournaments)
                <a href="{{ route('admin.tournaments.create') }}">Crear torneo</a>
            @endif
        </aside>
    </section>
</x-layouts.stc>
