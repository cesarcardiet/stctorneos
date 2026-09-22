<x-layouts.stc
    :title="$category->name.' | STC Torneos'"
    active="Categorías"
    :heading="$category->tournament->name"
    :subheading="'Paso 2 · '.$category->name"
    :tournament="$category->tournament"
    :category="$category"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.categories.index', ['tournament_id' => $category->tournament_id]) }}">← Todas las categorías</a>

    <nav class="category-tabs category-detail-tabs">
        <a class="active" href="{{ route('admin.categories.show', $category) }}">Resumen</a>
        <a href="#equipos">Equipos</a>
        <a href="{{ route('admin.categories.competition', $category) }}">Clasificación</a>
        <a href="{{ route('admin.categories.rankings', $category) }}">Rankings y goleadores</a>
        <a href="{{ route('admin.categories.edit', $category) }}">Editar</a>
    </nav>

    @php
        $categoryBanner = $category->bannerUrl();
    @endphp

    <section class="category-hero" style="--category-banner: url('{{ $categoryBanner }}')">
        <div class="category-date-card">
            <strong>Inicio:</strong> {{ $category->tournament->starts_at->format('d/m/Y') }}<br>
            <strong>Finalización:</strong> {{ $category->tournament->ends_at->format('d/m/Y') }}<br>
            <strong>Organizador:</strong> STC Torneos
        </div>
        <mark>{{ $category->name }}</mark>
        <a href="{{ route('admin.categories.edit', $category) }}">Editar</a>
    </section>

    <h2 class="category-section-title">Acerca de</h2>
    <section class="category-about-grid">
        <article class="stc-card">
            <header>
                <div>
                    <h3>Inscripciones</h3>
                </div>
            </header>
            @php
                $ws = $category->workspace();
                $registrationsOpen = (bool) ($ws['registrations_open'] ?? true);
            @endphp
            <p @class(['system-alert' => ! $registrationsOpen])>
                Estado actual: <strong>{{ $registrationsOpen ? 'Abiertas' : 'Cerradas' }}</strong>
                @unless ($category->tournamentRegistrationWindowOpen())
                    <br><small>El calendario del torneo ya pasó, pero abrir acá reactiva la carga para delegados.</small>
                @endunless
            </p>
            @if (! empty($ws['registration_info']))
                <p class="category-rules-copy">{{ $ws['registration_info'] }}</p>
            @endif
            <div class="hero-buttons">
                <form method="post" action="{{ route('admin.categories.registrations', $category) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="open" value="{{ $registrationsOpen ? 0 : 1 }}">
                    <button type="submit" class="ghost-hero">
                        {{ $registrationsOpen ? 'Cerrar inscripciones' : 'Abrir inscripciones' }}
                    </button>
                </form>
            </div>
            <p class="category-rules-copy">Con las inscripciones cerradas, los delegados pueden ver planteles pero no agregar ni editar jugadores. La administración sigue pudiendo modificar.</p>
            <form method="post" action="{{ route('admin.categories.registrations', $category) }}" class="category-registration-note">
                @csrf
                @method('PATCH')
                <input type="hidden" name="open" value="{{ $registrationsOpen ? 1 : 0 }}">
                <label>Mensaje para delegados (opcional)
                    <textarea name="registration_info" rows="3" placeholder="Ej: Las inscripciones cierran el viernes 15 a las 18 hs.">{{ $ws['registration_info'] ?? '' }}</textarea>
                </label>
                <button type="submit" class="ghost-hero">Guardar mensaje</button>
            </form>
        </article>

        <article class="stc-card">
            <header>
                <div>
                    <h3>Anexos</h3>
                </div>
            </header>
            <div class="category-links">
                @if ($category->tournament->rules_url)
                    <a href="{{ $category->tournament->rules_url }}" target="_blank" rel="noopener">Reglamento del torneo</a>
                @endif
                <a href="{{ route('admin.categories.competition', $category) }}">Forma de disputa</a>
                <a href="{{ route('admin.categories.rankings', $category) }}">Criterios de desempate</a>
            </div>
            @if ($category->rules)
                <p class="category-rules-copy">{{ $category->rules }}</p>
            @endif
            @if ($category->discipline_rules)
                <p class="category-rules-copy">{{ $category->discipline_rules }}</p>
            @endif
        </article>

        <article class="stc-card">
            <header>
                <div>
                    <h3>Información</h3>
                </div>
            </header>
            <div class="category-info-list">
                <span>Año / rango <strong>{{ $category->birth_year }}</strong></span>
                <span>Rama <strong>{{ $category->branch }}</strong></span>
                <span>Modalidad <strong>{{ $category->modalityLabel() }}</strong></span>
                <span>Formato <strong>{{ $category->competition_format }}</strong></span>
                <span>Equipos inscriptos <strong>{{ $category->teams_count }}</strong></span>
                <span>Partidos <strong>{{ $category->matches_count }}</strong></span>
                <span>Estado <strong>{{ $category->statusLabel() }}</strong></span>
            </div>
        </article>
    </section>

    <section class="category-config-grid">
        <article class="stc-card">
            <header>
                <div>
                    <h3>Plantel y partido</h3>
                </div>
            </header>
            <div class="category-info-list">
                <span>Mín. / máx. jugadores <strong>{{ $category->min_players }} / {{ $category->max_players }}</strong></span>
                <span>En cancha / suplentes <strong>{{ $category->players_on_field }} / {{ $category->substitutes }}</strong></span>
                <span>Períodos <strong>{{ $category->periods }} x {{ $category->period_duration }}'</strong></span>
                <span>Cupo de equipos <strong>{{ $category->team_limit }}</strong></span>
            </div>
        </article>
        <article class="stc-card">
            <header>
                <div>
                    <h3>Fases y llaves</h3>
                </div>
            </header>
            <div class="category-info-list">
                <span>Grupos / zonas <strong>{{ $category->groups_count }}</strong></span>
                <span>Equipos por grupo <strong>{{ $category->teams_per_group ?: '—' }}</strong></span>
                <span>Clasificados <strong>{{ $category->qualifiers_count }}</strong></span>
                <span>Fases <strong>{{ $category->phases ?: 'Sin cargar' }}</strong></span>
            </div>
            @if ($category->brackets)
                <p class="category-rules-copy">{{ $category->brackets }}</p>
            @endif
        </article>
        <article class="stc-card">
            <header>
                <div>
                    <h3>Clasificación</h3>
                </div>
            </header>
            <div class="category-info-list">
                <span>Puntos <strong>{{ $category->points_win }} / {{ $category->points_draw }} / {{ $category->points_loss }}</strong></span>
                <span>Desempate <strong>{{ collect($category->tiebreakers ?? [])->implode(' · ') ?: 'Sin cargar' }}</strong></span>
            </div>
            @if ($category->classification_criteria)
                <p class="category-rules-copy">{{ $category->classification_criteria }}</p>
            @endif
        </article>
    </section>

    <section id="equipos" class="category-teams-section">
        <h2>Equipos <small>{{ $category->teams_count }} equipos inscriptos</small></h2>
        <div class="category-team-table">
            <div class="table-head">
                <span>Equipo</span>
                <span>Delegación</span>
                <span>Jugadores</span>
                <span>Estado</span>
            </div>
            @forelse ($category->teams as $team)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.teams.show', $team)"
                            :src="$team->shieldUrl()"
                            :alt="$team->name"
                        >
                            {{ $team->name }}
                        </x-entity-cell>
                    </span>
                    <span>
                        @if ($team->delegation)
                            <x-entity-ref :href="route('admin.delegations.show', $team->delegation)" :src="$team->delegation->logoUrl()" :alt="$team->delegation->name">
                                {{ $team->delegation->name }}
                            </x-entity-ref>
                        @else
                            {{ $team->delegation_name ?? $team->city ?? '—' }}
                        @endif
                    </span>
                    <span>{{ $team->players_count }}</span>
                    <span @class(['tone-green' => $team->status === 'approved'])>{{ $team->statusLabel() }}</span>
                </div>
            @empty
                <div class="table-row">
                    <span><strong>Todavía no hay equipos en esta categoría.</strong></span>
                    <span>-</span>
                    <span>0</span>
                    <span>-</span>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
