<x-layouts.stc
    title="Delegaciones | STC Torneos"
    active="Delegaciones"
    heading="Delegaciones"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <form class="category-filter-bar delegation-filter-bar" method="get" action="{{ route('admin.delegations.index') }}">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar por nombre, delegado o ciudad">
        <select name="tournament_id">
            <option value="">Todos los torneos</option>
            @foreach ($accessibleTournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Todos los estados</option>
            @foreach (\App\Models\Delegation::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.delegations.index') }}">Limpiar</a>
    </form>

    <section class="admin-figma-kpis">
        @foreach ($stats as [$label, $value, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <section class="stc-card table-card delegation-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Listado de delegaciones</h3>
        </header>
        <div class="stc-table delegation-table admin-figma-table admin-figma-table--delegations">
            <div class="table-head">
                <span>Delegación</span>
                <span>Delegado</span>
                <span>Categorías</span>
                <span>Estado</span>
                <span>Acción</span>
            </div>

            @forelse ($delegations as $delegation)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.delegations.show', $delegation)"
                            :src="$delegation->logoUrl()"
                            :alt="$delegation->name"
                        >
                            {{ $delegation->name }}
                            <x-slot:subtitle>
                                @if ($delegation->tournament)
                                    <a href="{{ route('admin.tournaments.show', $delegation->tournament) }}">{{ $delegation->tournament->name }}</a>
                                @endif
                                · {{ $delegation->originLabel() }}
                            </x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>{{ $delegation->delegate_name ?: '—' }}</span>
                    <span>{{ $delegation->categoryYearsLabel() }}</span>
                    <span class="admin-status is-{{ $delegation->status }}">{{ $delegation->statusLabel() }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.delegations.show', $delegation) }}">{{ $delegation->reviewActionLabel() }}</a>
                        <a href="{{ route('admin.delegations.edit', $delegation) }}">Editar</a>
                        <a href="{{ route('admin.teams.index', ['delegation_id' => $delegation->id]) }}">Equipos</a>
                        <form method="post" action="{{ route('admin.delegations.destroy', $delegation) }}" data-confirm="¿Eliminar esta delegación y sus equipos vinculados? Esta acción no se puede deshacer.">
                            @csrf
                            @method('DELETE')
                            <button class="danger" type="submit">Eliminar</button>
                        </form>
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span>
                        <strong>No hay delegaciones con esos filtros.</strong>
                        <small>Cambiá filtros o creá una delegación desde Nuevo.</small>
                    </span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.delegations.index') }}">Ver todas</a>
                    </span>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
