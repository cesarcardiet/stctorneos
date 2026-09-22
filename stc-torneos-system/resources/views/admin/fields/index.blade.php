<x-layouts.stc
    title="Campos y Sedes | STC Torneos"
    active="Campos"
    heading="Campos y Sedes"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <section class="admin-figma-kpis">
        @foreach ($stats as [$value, $label, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <form class="category-filter-bar player-filter-bar" method="get" action="{{ route('admin.fields.index') }}">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar sede, cancha o ciudad">
        <select name="venue_id">
            <option value="">Todas las sedes</option>
            @foreach ($venues as $venue)
                <option value="{{ $venue->id }}" @selected((string) $filters['venue_id'] === (string) $venue->id)>{{ $venue->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Estado: todos</option>
            @foreach (\App\Models\Field::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="tournament_id">
            <option value="">Todos los torneos</option>
            @foreach ($accessibleTournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.fields.index') }}">Limpiar</a>
    </form>

    <section class="fields-overview-grid">
        <article class="fields-pitch-card">
            <h3>Mapa de canchas</h3>
            @include('admin.fields.partials.map')
        </article>

        <article class="fields-availability-card">
            <h3>Disponibilidad de sedes</h3>
            <div class="delegation-figma-table fields-figma-table fields-availability-table">
                <div class="table-head">
                    <span>Sede</span>
                    <span>Cancha</span>
                    <span>Horario</span>
                    <span>Estado</span>
                    <span>Conflicto</span>
                    <span>Acción</span>
                </div>
                @forelse ($fields as $field)
                    <div @class(['table-row', 'has-conflict' => $field->hasConflict()])>
                    <span>
                        <x-entity-cell
                            :href="route('admin.fields.show', $field)"
                            :src="$field->imageUrl()"
                            :alt="$field->venue?->name ?? $field->name"
                        >
                            {{ $field->venue?->name }}
                            <x-slot:subtitle>{{ $field->venue?->city }}</x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                        <span>{{ $field->name }}</span>
                        <span>{{ $field->scheduleLabel() }}</span>
                        <span>{{ $field->availabilityLabel() }}</span>
                        <span>{{ $field->conflictLabel() }}</span>
                        <span class="category-actions">
                            <a href="{{ route('admin.fields.show', $field) }}">Ver</a>
                            <a href="{{ route('admin.fields.edit', $field) }}">Editar</a>
                            <a href="{{ route('admin.fixture.index', ['field_id' => $field->id]) }}">Fixture</a>
                        </span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>No hay canchas con esos filtros.</strong></div>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.stc>
