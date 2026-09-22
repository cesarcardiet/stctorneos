<x-layouts.stc
    title="Categorías | STC Torneos"
    active="Categorías"
    heading="Categorías"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <form class="category-filter-bar" method="get" action="{{ route('admin.categories.index') }}">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar categoría, año, modalidad o formato">
        <select name="tournament_id">
            <option value="">Todos los torneos</option>
            @foreach ($accessibleTournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
            @endforeach
        </select>
        <select name="status">
            @foreach (['all' => 'Todos los estados', 'active' => 'Activas', 'inactive' => 'Inactivas', 'draft' => 'Borrador'] as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="modality">
            <option value="all" @selected($filters['modality'] === 'all')>Todas las modalidades</option>
            @foreach ($modalities as $modality)
                <option value="{{ $modality }}" @selected($filters['modality'] === $modality)>{{ $modality }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.categories.index') }}">Limpiar</a>
    </form>

    <section class="admin-figma-kpis">
        @foreach ($stats as [$label, $value, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <section class="stc-card table-card category-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Categorías</h3>
        </header>
        <div class="stc-table category-table admin-figma-table">
            <div class="table-head">
                <span>Categoría</span>
                <span>Año</span>
                <span>Formato</span>
                <span>Equipos</span>
                <span>Estado</span>
                <span>Inscripciones</span>
                <span>Acciones</span>
            </div>

            @forelse ($categories as $category)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.categories.show', $category)"
                            :src="$category->bannerUrl()"
                            :alt="$category->name"
                        >
                            {{ $category->name }}
                            <x-slot:subtitle>
                                @if ($category->tournament)
                                    <a href="{{ route('admin.tournaments.show', $category->tournament) }}">{{ $category->tournament->name }}</a>
                                @endif
                                · {{ $category->branch }} · {{ $category->modalityLabel() }}
                            </x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>{{ $category->birth_year }}</span>
                    <span>{{ $category->competition_format }}</span>
                    <span>{{ $category->teams_count }}</span>
                    <span class="category-inline-control">
                        <form class="inline-status-form" method="post" action="{{ route('admin.categories.status', $category) }}">
                            @csrf
                            @method('PATCH')
                            <select name="status">
                                @foreach (\App\Models\Category::statusLabels() as $value => $label)
                                    <option value="{{ $value }}" @selected($category->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit">Cambiar</button>
                        </form>
                    </span>
                    <span class="category-inline-control">
                        @php $registrationsOpen = $category->registrationsOpen(); @endphp
                        <strong>{{ $registrationsOpen ? 'Abiertas' : 'Cerradas' }}</strong>
                        <form class="inline-status-form" method="post" action="{{ route('admin.categories.registrations', $category) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="open" value="{{ $registrationsOpen ? 0 : 1 }}">
                            <button type="submit">{{ $registrationsOpen ? 'Cerrar' : 'Abrir' }}</button>
                        </form>
                    </span>
                    <span class="category-actions">
                        <a href="{{ route('admin.categories.show', $category) }}">Ver</a>
                        <a href="{{ route('admin.categories.edit', $category) }}">Editar</a>
                        <form method="post" action="{{ route('admin.categories.duplicate', $category) }}">
                            @csrf
                            <button type="submit">Duplicar</button>
                        </form>
                        <a href="{{ route('admin.categories.competition', $category) }}">Clasificación</a>
                        <form method="post" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="¿Eliminar esta categoría y sus equipos/partidos relacionados? Esta acción no se puede deshacer.">
                            @csrf
                            @method('DELETE')
                            <button class="danger" type="submit">Eliminar</button>
                        </form>
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span>
                        <strong>No hay categorías con esos filtros.</strong>
                        <small>Cambiá filtros o creá una categoría desde Nuevo.</small>
                    </span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.categories.index') }}">Ver todas</a>
                    </span>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
