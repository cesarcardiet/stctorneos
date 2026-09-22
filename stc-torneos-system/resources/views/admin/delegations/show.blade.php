<x-layouts.stc
    :title="$delegation->name.' | STC Torneos'"
    active="Delegaciones"
    heading="Detalle Delegación"
    :subheading="$delegation->name.' · delegado '.($delegation->delegate_name ?: 'sin asignar').' · estado '.mb_strtolower($delegation->statusLabel()).'.'"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.delegations.index') }}">← Delegaciones</a>

    <section class="admin-figma-hero">
        <img src="{{ $delegation->logoUrl() }}" alt="{{ $delegation->name }}">
        <div>
            <h2>{{ strtoupper($delegation->name) }}</h2>
            <p>Delegado: {{ $delegation->delegate_name ?: 'Sin asignar' }} · {{ $delegation->country ?: $delegation->originLabel() }}</p>
            <div class="category-actions admin-figma-hero-actions">
                <a class="admin-figma-cta" href="{{ route('admin.delegations.edit', $delegation) }}">EDITAR DELEGACIÓN</a>
                <a href="{{ route('admin.delegations.credential', $delegation) }}" target="_blank" rel="noopener">Credencial QR</a>
                <a href="{{ route('admin.delegations.edit', $delegation) }}#delegado">Asignar delegado</a>
                <a href="{{ route('admin.teams.index', ['delegation_id' => $delegation->id]) }}">Equipos</a>
            </div>
        </div>
        <article class="tone-cyan"><strong>{{ $delegation->teams_count }}</strong><span>Equipos</span></article>
        <article class="tone-green"><strong>{{ $inscription['registered'] }}</strong><span>Jugadores</span></article>
        <article class="tone-yellow"><strong>{{ $inscription['pending'] }}</strong><span>Pendientes</span></article>
    </section>

    <h2 class="category-section-title">Avance de inscripción</h2>
    <section class="admin-figma-kpis admin-figma-kpis--five">
        @foreach ([
            ['Jugadores registrados', $inscription['registered'], 'blue'],
            ['Fichas completas', $inscription['complete'], 'green'],
            ['Fichas pendientes', $inscription['pending'], 'yellow'],
            ['Jugadores habilitados', $inscription['enabled'], 'cyan'],
            ['Documentación pendiente', $inscription['docs_pending'], 'red'],
        ] as [$label, $value, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <section class="category-about-grid">
        <article class="stc-card">
            <header>
                <div>
                    <h3>Información institucional</h3>
                </div>
            </header>
            <div class="category-info-list">
                <span>Nombre <strong>{{ $delegation->name }}</strong></span>
                <span>Procedencia <strong>{{ $delegation->originLabel() }}</strong></span>
                <span>Contacto <strong>{{ $delegation->delegate_email ?: $delegation->delegate_phone ?: 'Sin cargar' }}</strong></span>
                <span>Estado <strong>{{ $delegation->statusLabel() }}</strong></span>
            </div>
            @if ($delegation->notes)
                <p class="category-rules-copy">{{ $delegation->notes }}</p>
            @endif
        </article>

        <article class="stc-card">
            <header>
                <div>
                    <h3>Responsables</h3>
                </div>
            </header>
            <div class="category-info-list">
                <span>Delegado principal <strong>{{ $delegation->delegate_name }}</strong></span>
                <span>Email <strong>{{ $delegation->delegate_email ?: '—' }}</strong></span>
                <span>Teléfono <strong>{{ $delegation->delegate_phone ?: '—' }}</strong></span>
            </div>
            @forelse ($delegation->additionalContacts() as $contact)
                <p class="category-rules-copy">{{ $contact['name'] }} · {{ $contact['role'] }}{{ $contact['phone'] ? ' · '.$contact['phone'] : '' }}</p>
            @empty
                <p class="category-rules-copy">Sin responsables adicionales.</p>
            @endforelse
        </article>
    </section>

    <section class="stc-card table-card delegation-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Equipos asociados</h3>
            <a href="{{ route('admin.teams.create', ['delegation' => $delegation->id]) }}">Agregar equipo</a>
        </header>
        <div class="stc-table delegation-teams-table admin-figma-table admin-figma-table--club-teams">
            <div class="table-head">
                <span>Equipo</span>
                <span>Jugadores</span>
                <span>Estado</span>
                <span>Acciones</span>
            </div>
            @forelse ($delegation->teams as $team)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.teams.show', $team)"
                            :src="$team->shieldUrl()"
                            :alt="$team->name"
                        >
                            {{ $team->name }}
                            <x-slot:subtitle>
                                @if ($team->category)
                                    <a href="{{ route('admin.categories.show', $team->category) }}">{{ $team->category->name ?? $team->category->birth_year }}</a>
                                @else
                                    —
                                @endif
                            </x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>{{ $team->players_count }} jugadores</span>
                    <span class="admin-status is-{{ $team->status }}">{{ $team->statusLabel() }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.teams.show', $team) }}">Ver</a>
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span><strong>Todavía no hay equipos en esta delegación.</strong></span>
                    <span>0</span>
                    <span>-</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.teams.create', ['delegation' => $delegation->id]) }}">Crear</a>
                    </span>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
