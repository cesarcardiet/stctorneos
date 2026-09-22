<x-layouts.stc
    title="Equipos | STC Torneos"
    active="Equipos"
    heading="Equipos"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <form class="category-filter-bar team-filter-bar" method="get" action="{{ route('admin.teams.index') }}">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar equipo o delegación">
        <select name="tournament_id">
            <option value="">Todos los torneos</option>
            @foreach ($accessibleTournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
            @endforeach
        </select>
        <select name="delegation_id">
            <option value="">Todas las delegaciones</option>
            @foreach ($delegations as $delegation)
                <option value="{{ $delegation->id }}" @selected((string) $filters['delegation_id'] === (string) $delegation->id)>{{ $delegation->name }}</option>
            @endforeach
        </select>
        <select name="category_id">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Todos los estados</option>
            @foreach (\App\Models\Team::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.teams.index') }}">Limpiar</a>
    </form>

    <section class="admin-figma-kpis">
        @foreach ($stats as [$label, $value, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <section class="stc-card table-card team-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Equipos inscritos</h3>
        </header>
        <div class="stc-table team-table admin-figma-table admin-figma-table--teams">
            <div class="table-head">
                <span>Equipo</span>
                <span>Delegación</span>
                <span>Categoría</span>
                <span>Estado</span>
                <span>Jugadores</span>
                <span>Habilitados</span>
                <span>Acciones</span>
            </div>

            @forelse ($teams as $team)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.teams.show', $team)"
                            :src="$team->shieldUrl()"
                            :alt="$team->name"
                        >
                            {{ $team->name }}
                            <x-slot:subtitle>
                                @if ($team->tournament)
                                    <a href="{{ route('admin.tournaments.show', $team->tournament) }}">{{ $team->tournament->name }}</a>
                                @endif
                                {{ $team->group_name ? ' · Grupo '.$team->group_name : '' }}
                            </x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>
                        @if ($team->delegation)
                            <x-entity-ref :href="route('admin.delegations.show', $team->delegation)" :src="$team->delegation->logoUrl()" :alt="$team->delegation->name">
                                {{ $team->delegation->name }}
                            </x-entity-ref>
                        @else
                            {{ $team->delegation_name ?: '—' }}
                        @endif
                    </span>
                    <span>
                        @if ($team->category)
                            <x-entity-ref :href="route('admin.categories.show', $team->category)" :src="$team->category->bannerUrl()" :alt="$team->category->name">
                                {{ $team->category->birth_year ?? $team->category->name }}
                            </x-entity-ref>
                            <small>{{ $team->category->name }}</small>
                        @else
                            —
                        @endif
                    </span>
                    <span class="admin-status is-{{ $team->status }}">{{ $team->statusLabel() }}</span>
                    <span>{{ $team->players_count }}/{{ $team->player_capacity }}</span>
                    <span>{{ $team->enabled_players_count }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.teams.show', $team) }}">Ver</a>
                        <a href="{{ route('admin.teams.edit', $team) }}">Editar</a>
                        <a href="{{ route('admin.players.index', ['team_id' => $team->id]) }}">Plantel</a>
                        <a href="{{ route('admin.fixture.index', ['team_id' => $team->id]) }}">Fixture</a>
                        <form method="post" action="{{ route('admin.teams.destroy', $team) }}" data-confirm="¿Eliminar este equipo y sus partidos relacionados? Esta acción no se puede deshacer.">
                            @csrf
                            @method('DELETE')
                            <button class="danger" type="submit">Eliminar</button>
                        </form>
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span>
                        <strong>No hay equipos con esos filtros.</strong>
                        <small>Cambiá filtros o creá un equipo desde Nuevo.</small>
                    </span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.teams.index') }}">Ver todos</a>
                    </span>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
