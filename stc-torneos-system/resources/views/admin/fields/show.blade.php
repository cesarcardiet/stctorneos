<x-layouts.stc
    :title="($field->name ?? 'Cancha').' | STC Torneos'"
    active="Campos"
    heading="Detalle Sede / Cancha"
    :subheading="($field->venue?->name ?? 'Sede').' · '.$field->name.' · '.$field->detailStatusPhrase().'.'"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <section class="field-hero-card">
        <img src="{{ $field->imageUrl() }}" alt="{{ $field->venue?->name }}">
        <div class="field-hero-copy">
            <h2>{{ strtoupper($field->venue?->name ?? 'Sede') }}</h2>
            <p>{{ $field->heroMeta() }}</p>
        </div>
        <div class="field-hero-actions">
            <a class="stc-button" href="{{ route('admin.fields.edit', $field) }}">Editar cancha</a>
            @if ($field->status === 'maintenance')
                <form method="post" action="{{ route('admin.fields.status', $field) }}">
                    @csrf
                    @method('PATCH')
                    <button class="stc-button" type="submit" name="status" value="available">Reactivar cancha</button>
                </form>
            @else
                <form method="post" action="{{ route('admin.fields.status', $field) }}" data-confirm="¿Bloquear temporalmente esta cancha por mantenimiento?">
                    @csrf
                    @method('PATCH')
                    <button class="stc-button" type="submit" name="status" value="maintenance">Bloquear temporalmente</button>
                </form>
            @endif
            <a class="stc-button" href="{{ route('admin.fixture.index', ['field_id' => $field->id]) }}">Ver fixture</a>
            @if ($field->venue)
                <a class="stc-button" href="{{ $field->venue->mapUrl() }}" target="_blank" rel="noopener">Ver mapa</a>
            @endif
        </div>
        @foreach ($venueStats as [$value, $label, $tone])
            <article class="tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    @if ($field->hasConflict())
        <div class="system-alert">Esta cancha tiene conflicto de programación o está bloqueada con partidos pendientes.</div>
    @endif

    <section class="delegation-form-card">
        <h3>Datos operativos</h3>
        <ul class="player-data-list">
            <li><span>Torneo</span><strong>{{ $field->venue?->tournament?->name ?? '—' }}</strong></li>
            <li><span>Dirección</span><strong>{{ $field->venue?->address ?: 'Sin cargar' }}</strong></li>
            <li><span>Ciudad</span><strong>{{ $field->venue?->city ?: '—' }}</strong></li>
            <li><span>Superficie</span><strong>{{ $field->surface }}</strong></li>
            <li><span>Modalidad</span><strong>{{ $field->modality ?: '—' }}</strong></li>
            <li><span>Horario</span><strong>{{ $field->scheduleLabel() }}</strong></li>
            <li><span>Iluminación</span><strong>{{ $field->lightingLabel() }}</strong></li>
            <li><span>Observaciones</span><strong>{{ $field->notes ?: ($field->venue?->notes ?: 'Sin observaciones') }}</strong></li>
        </ul>
    </section>

    <section class="fields-schedule-card">
        @forelse ($schedule as $match)
            <a class="fields-schedule-row" href="{{ route('admin.fixture.show', $match) }}">
                <strong>{{ $match->agendaDateLabel() }}</strong>
                <span>{{ $match->agendaLine() }}</span>
                <b class="agenda-{{ $match->agendaStatusTone() }}">{{ $match->agendaStatusLabel() }}</b>
            </a>
        @empty
            <p>No hay partidos programados en esta cancha.</p>
        @endforelse
    </section>

    @if (($venueFields ?? collect())->count() > 1)
        <section class="stc-card table-card team-list-panel" style="margin-top: 1.2rem;">
            <header class="admin-list-heading">
                <h3>Otras canchas de la sede</h3>
            </header>
            <div class="stc-table team-roster-table">
                <div class="table-head">
                    <span>Cancha</span>
                    <span>Horario</span>
                    <span>Estado</span>
                    <span>Acciones</span>
                </div>
                @foreach ($venueFields as $other)
                    @if ((int) $other->id === (int) $field->id)
                        @continue
                    @endif
                    <div class="table-row">
                        <span>{{ $other->name }}</span>
                        <span>{{ $other->scheduleLabel() }}</span>
                        <span>{{ $other->availabilityLabel() }}</span>
                        <span class="category-actions">
                            <a href="{{ route('admin.fields.show', $other) }}">Ver</a>
                            <a href="{{ route('admin.fields.edit', $other) }}">Editar</a>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.stc>
