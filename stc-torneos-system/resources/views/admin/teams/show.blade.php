<x-layouts.stc
    :title="$team->name.' | STC Torneos'"
    active="Equipos"
    heading="Detalle de equipo"
    :subheading="$team->name.' · '.($team->category?->name ?? 'Sin categoría').' · '.$team->statusLabel()"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.teams.index') }}">← Equipos</a>

    <section class="delegation-hero-card">
        <img src="{{ $team->shieldUrl() }}" alt="{{ $team->name }}">
        <div>
            <h2>{{ strtoupper($team->name) }}</h2>
            <p>
                @if ($team->delegation)
                    <a href="{{ route('admin.delegations.show', $team->delegation) }}">{{ $team->delegation->name }}</a>
                @else
                    {{ $team->delegation_name }}
                @endif
                ·
                @if ($team->category)
                    <a href="{{ route('admin.categories.show', $team->category) }}">{{ $team->category->name }}</a>
                @endif
                · {{ $team->category?->modalityLabel() }}
            </p>
            <p>
                @if ($team->tournament)
                    <a href="{{ route('admin.tournaments.show', $team->tournament) }}">{{ $team->tournament->name }}</a>
                @endif
                {{ $team->group_name ? ' · Grupo '.$team->group_name : '' }}
            </p>
            <div class="category-actions">
                <a href="{{ route('admin.teams.edit', $team) }}">Editar</a>
                <a href="{{ route('admin.players.index', ['team_id' => $team->id]) }}">Lista de Buena Fe</a>
                <a href="{{ route('admin.players.create', ['team' => $team->id]) }}">Agregar jugador</a>
                <a href="{{ route('admin.fixture.index', ['team_id' => $team->id]) }}">Fixture</a>
                <form method="post" action="{{ route('admin.teams.destroy', $team) }}" data-confirm="¿Eliminar {{ $team->name }} y sus partidos relacionados? Esta acción no se puede deshacer.">
                    @csrf
                    @method('DELETE')
                    <button class="ghost-action" type="submit">Eliminar equipo</button>
                </form>
            </div>
        </div>
        <article><strong>{{ $team->players_count }}</strong><span>Plantel</span></article>
        <article><strong>{{ $team->players->where('status', 'enabled')->count() }}</strong><span>Habilitados</span></article>
        <article><strong>{{ $stats['played'] }}</strong><span>Jugados</span></article>
    </section>

    <h2 class="category-section-title">Estadísticas</h2>
    <section class="kpi-grid category-crud-kpis">
        @foreach ([
            ['Jugados', $stats['played']],
            ['Ganados', $stats['won']],
            ['Empatados', $stats['drawn']],
            ['Perdidos', $stats['lost']],
            ['Goles', $stats['gf'].' / '.$stats['ga']],
        ] as [$label, $value])
            <article class="kpi-card">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <section class="category-about-grid">
        <article class="stc-card">
            <header>
                <div>
                    <h3>Información general</h3>
                </div>
            </header>
            <div class="category-info-list">
                <span>Delegación <strong>
                    @if ($team->delegation)
                        <a href="{{ route('admin.delegations.show', $team->delegation) }}">{{ $team->delegation->name }}</a>
                    @else
                        {{ $team->delegation_name ?: '—' }}
                    @endif
                </strong></span>
                <span>Categoría <strong>
                    @if ($team->category)
                        <a href="{{ route('admin.categories.show', $team->category) }}">{{ $team->category->name }}</a>
                    @else
                        —
                    @endif
                </strong></span>
                <span>Modalidad <strong>{{ $team->category?->modalityLabel() ?? '—' }}</strong></span>
                <span>Camisetas <strong>{{ trim(($team->home_kit ?? '').' / '.($team->away_kit ?? ''), ' /') ?: '—' }}</strong></span>
                <span>Cupo <strong>{{ $team->player_capacity }}</strong></span>
                <span>Estado <strong>{{ $team->statusLabel() }}</strong></span>
            </div>
            @if ($team->notes)
                <p class="category-rules-copy">{{ $team->notes }}</p>
            @endif
        </article>

        <article class="stc-card" id="cuerpo-tecnico">
            <header>
                <div>
                    <h3>Cuerpo técnico</h3>
                </div>
                @if ($availableRoles !== [])
                    <a href="{{ route('admin.teams.staff.create', $team) }}">Agregar</a>
                @endif
            </header>
            <div class="category-info-list">
                @forelse ($team->staffMembers as $member)
                    <span>
                        {{ $member->roleLabel() }}
                        <strong>{{ $member->fullName() }} · {{ $member->statusLabel() }}</strong>
                    </span>
                @empty
                    <span>Sin cuerpo técnico cargado <strong>—</strong></span>
                @endforelse
            </div>
            @if ($team->staffMembers->isNotEmpty())
                <div class="category-actions" style="margin-top: .9rem; justify-content: flex-start;">
                    @foreach ($team->staffMembers as $member)
                        <a href="{{ route('admin.teams.staff.edit', [$team, $member]) }}">Editar {{ $member->roleLabel() }}</a>
                        <a href="{{ route('admin.teams.staff.credential', [$team, $member]) }}">Credencial</a>
                    @endforeach
                </div>
            @endif
        </article>
    </section>

    <section class="stc-card table-card team-list-panel">
        <header class="admin-list-heading">
            <h3>Plantel</h3>
            <a href="{{ route('admin.players.index', ['team_id' => $team->id]) }}">Lista de Buena Fe</a>
        </header>
        <div class="stc-table team-roster-table">
            <div class="table-head">
                <span>Jugador</span>
                <span>Ficha</span>
                <span>Documentación</span>
                <span>Habilitación</span>
                <span></span>
            </div>
            @forelse ($team->players as $player)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.players.show', $player)"
                            :src="$player->listPhotoUrl()"
                            :alt="$player->fullName()"
                            shape="round"
                        >
                            {{ $player->fullName() }}
                            <x-slot:subtitle>{{ $player->formattedDocument() }}</x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>{{ $player->fileStatusLabel() }}</span>
                    <span>{{ $player->documentationSummary() }}</span>
                    <span>{{ $player->eligibilityLabel() }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.players.show', $player) }}">Ver ficha</a>
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span><strong>Todavía no hay jugadores en este plantel.</strong></span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.players.create', ['team' => $team->id]) }}">Agregar</a>
                    </span>
                </div>
            @endforelse
        </div>
    </section>

    <section class="category-about-grid">
        <article class="stc-card">
            <header>
                <div>
                    <h3>Próximos partidos</h3>
                </div>
                <a href="{{ route('admin.fixture.index', ['team_id' => $team->id]) }}">Ver fixture</a>
            </header>
            <div class="schedule-list">
                @forelse ($upcoming->take(5) as $match)
                    <div class="schedule-row">
                        <time>{{ $match->scheduled_at?->format('d/m H:i') ?? '—' }}</time>
                        <span>
                            <strong>{{ $match->title() }}</strong>
                            <small>{{ $match->field?->name }} · {{ $match->stage }}</small>
                        </span>
                        <mark>{{ $match->statusLabel() }}</mark>
                    </div>
                @empty
                    <p class="category-rules-copy">No hay partidos programados.</p>
                @endforelse
            </div>
        </article>

        <article class="stc-card">
            <header>
                <div>
                    <h3>Resultados</h3>
                </div>
            </header>
            <div class="schedule-list">
                @forelse ($results->take(5) as $match)
                    <div class="schedule-row">
                        <time>{{ $match->scheduled_at?->format('d/m') ?? '—' }}</time>
                        <span>
                            <strong>{{ $match->title() }}</strong>
                            <small>{{ $match->scoreLine() }} · {{ $match->stage }}</small>
                        </span>
                        <mark>{{ $match->statusLabel() }}</mark>
                    </div>
                @empty
                    <p class="category-rules-copy">Todavía no hay resultados cargados.</p>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.stc>
