<x-layouts.stc
    title="Árbitros y asistentes | STC Torneos"
    active="Fixture"
    heading="Agenda de árbitros"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.fixture.index') }}">← Volver al fixture</a>

    <div class="category-actions fixture-toolbar">
        <a href="{{ route('admin.fixture.jornada') }}">Mapa de la fecha</a>
        <a href="{{ route('admin.fixture.index') }}">Agenda de partidos</a>
    </div>

    @forelse ($rows as $row)
        <section class="sheets-list-card" style="margin-top: 1.2rem;">
            <h3>{{ $row['official']->name }} · {{ $row['official']->roleLabel() }}</h3>
            <div class="delegation-figma-table fixture-figma-table">
                <div class="table-head">
                    <span>Partido</span>
                    <span>Fecha</span>
                    <span>Rol en el partido</span>
                    <span>Cancha</span>
                    <span>Estado</span>
                    <span>Acción</span>
                </div>
                @forelse ($row['matches'] as $match)
                    <div class="table-row">
                        <span><strong>{{ $match->title() }}</strong><small>{{ $match->category?->name }}</small></span>
                        <span>{{ $match->agendaDateLabel() }}</span>
                        <span>{{ (int) $match->referee_user_id === (int) $row['official']->id ? 'Árbitro' : 'Asistente' }}</span>
                        <span>{{ $match->field?->name ?: 'Sin cancha' }}</span>
                        <span>{{ $match->statusLabel() }}</span>
                        <span><a href="{{ route('admin.fixture.show', $match) }}">Ver</a></span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>Sin partidos asignados.</strong></div>
                @endforelse
            </div>
        </section>
    @empty
        <div class="category-empty-state"><strong>No hay árbitros ni asistentes de mesa activos en tu alcance.</strong></div>
    @endforelse
</x-layouts.stc>
