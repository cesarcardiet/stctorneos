<x-layouts.stc title="Dashboard Admin | STC Torneos" active="Dashboard" heading="Dashboard Admin" :subheading="$tournament?->name ?? 'Resumen operativo de todos los torneos'">
    @php
        $canFixture = auth()->user()?->hasPermission('matches.manage');
        $scopeLabel = auth()->user()?->canAccessAllTournaments()
            ? ($tournament ? 'Admin General · torneo seleccionado' : 'Admin General · todos los torneos')
            : 'Admin Torneo · solo torneos asignados';
    @endphp
    <section class="hero-panel">
        <div>
            <h2>{{ $tournament?->name ?? 'Todos los torneos' }}</h2>
            <div class="hero-buttons">
                @if ($availableTournaments->count() > 1 || $canSeeAllTournaments)
                    <form method="get" action="{{ route('dashboard') }}" class="dashboard-scope-form">
                        <span class="dashboard-scope-label">Torneo</span>
                        <div class="dashboard-hero-row">
                            <select name="tournament_id" onchange="this.form.submit()">
                                @if ($canSeeAllTournaments)
                                    <option value="all" @selected($tournament === null)>Todos los torneos</option>
                                @endif
                                @foreach ($availableTournaments as $item)
                                    <option value="{{ $item->id }}" @selected((int) $tournament?->id === (int) $item->id)>{{ $item->name }}</option>
                                @endforeach
                            </select>
                            @if ($tournament)
                                <a href="{{ route('admin.tournaments.show', $tournament) }}">Ver torneo</a>
                            @elseif (auth()->user()?->hasPermission('tournaments.manage'))
                                <a href="{{ route('admin.tournaments.index') }}">Ver torneos</a>
                            @endif
                        </div>
                    </form>
                @elseif ($tournament)
                    <a href="{{ route('admin.tournaments.show', $tournament) }}">Ver torneo</a>
                @elseif (auth()->user()?->hasPermission('tournaments.manage'))
                    <a href="{{ route('admin.tournaments.index') }}">Ver torneos</a>
                @endif
            </div>
        </div>
        <p>
            Estado: <strong>{{ $tournament?->statusLabel() ?? 'Vista global' }}</strong>
            · {{ $scopeLabel }}
            @if ($tournament)
                · {{ $tournament->starts_at?->format('d/m/Y') ?? 'Sin fecha' }} al {{ $tournament->ends_at?->format('d/m/Y') ?? 'sin cierre' }}
                · {{ $tournament->location ?? $tournament->city ?? 'Sin sede' }}
            @else
                · {{ $availableTournaments->count() }} torneo{{ $availableTournaments->count() === 1 ? '' : 's' }}
            @endif
        </p>
        <figure class="trophy-card">
            <img src="{{ $tournament?->logoUrl() ?? asset('images/stc-logo.png') }}" alt="{{ $tournament?->name ?? 'STC Torneos' }}" data-ws-tourney-logo>
        </figure>
    </section>

    <section class="kpi-grid dashboard-kpi-grid">
        @foreach ($stats as [$label, $value, $tone, $url])
            @if ($url)
                <a class="kpi-card" href="{{ $url }}">
                    <strong>{{ $value }}</strong>
                    <span>{{ $label }}</span>
                </a>
            @else
                <article class="kpi-card">
                    <strong>{{ $value }}</strong>
                    <span>{{ $label }}</span>
                </article>
            @endif
        @endforeach
    </section>

    <section class="stc-card dashboard-actions-card">
        <header>
            <div>
                <p class="stc-eyebrow">Acciones</p>
                <h3>Ir al contexto operativo</h3>
            </div>
        </header>
        <div class="quick-actions dashboard-action-grid">
            @forelse ($actions as $action)
                <a href="{{ $action['url'] }}">
                    <strong>{{ $action['label'] }}</strong>
                    <span>{{ $action['hint'] }}</span>
                </a>
            @empty
                <p class="schedule-empty">No hay acciones disponibles para tu rol.</p>
            @endforelse
        </div>
    </section>

    <section class="dashboard-grid">
        <article class="stc-card match-card">
            <header>
                <h3>Partidos en vivo</h3>
            </header>
            @forelse ($liveMatches as $match)
                @if ($canFixture)
                    <a class="match-row" href="{{ route('admin.fixture.show', $match) }}">
                @else
                    <div class="match-row">
                @endif
                    <span>{{ $match->homeTeam?->name ?? 'Local' }} vs {{ $match->awayTeam?->name ?? 'Visitante' }}</span>
                    <strong>{{ $match->home_score }} - {{ $match->away_score }}</strong>
                    <small>{{ $match->minute }}</small>
                @if ($canFixture)
                    </a>
                @else
                    </div>
                @endif
            @empty
                <p class="schedule-empty">No hay partidos en vivo ahora.</p>
            @endforelse
        </article>

        <article class="stc-card alerts-card">
            <header>
                <h3>Alertas y tareas pendientes</h3>
            </header>
            <ul class="alert-list">
                @foreach ($alerts as [$tone, $message, $url])
                    <li>
                        <span class="{{ $tone }}"></span>
                        @if ($url)
                            <a href="{{ $url }}">{{ $message }}</a>
                        @else
                            {{ $message }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </article>
    </section>

    <section class="dashboard-wide-grid schedule-section">
        <article class="stc-card table-card schedule-card">
            <header>
                <div>
                    <p class="stc-eyebrow">Fixture</p>
                    <h3>{{ $scheduleHeading }}</h3>
                </div>
                @if ($canFixture)
                    <a href="{{ route('admin.fixture.index', array_filter(['tournament_id' => $tournament?->id, 'date' => $todaySchedule->first()?->scheduled_at?->toDateString()])) }}">Ver jornada</a>
                @endif
            </header>

            <div class="schedule-list">
                @forelse ($todaySchedule as $match)
                    @if ($canFixture)
                        <a class="schedule-row" href="{{ route('admin.fixture.show', $match) }}">
                    @else
                        <div class="schedule-row">
                    @endif
                        <time>{{ $match->scheduled_at->format('H:i') }}</time>
                        <span>
                            <strong>{{ $match->homeTeam?->name ?? 'Local' }} vs {{ $match->awayTeam?->name ?? 'Visitante' }}</strong>
                            <small>{{ $match->category?->name }} · {{ $match->stage }} · {{ $match->field?->name }}</small>
                        </span>
                        <mark class="status-pill status-{{ $match->status }}">
                            {{ $match->statusLabel() }}
                        </mark>
                    @if ($canFixture)
                        </a>
                    @else
                        </div>
                    @endif
                @empty
                    <p class="schedule-empty">No hay partidos cargados para esta jornada.</p>
                @endforelse
            </div>
        </article>

        <article class="stc-card summary-card">
            <header>
                <div>
                    <p class="stc-eyebrow">Fixture</p>
                    <h3>Próximas jornadas</h3>
                </div>
            </header>
            <div class="summary-stack">
                @forelse ($upcomingJornadas as $jornada)
                    @if ($jornada['url'])
                        <a href="{{ $jornada['url'] }}">
                            <strong>{{ $jornada['date']->format('d/m') }}</strong>
                            <span>{{ $jornada['count'] }} partido{{ $jornada['count'] === 1 ? '' : 's' }}</span>
                        </a>
                    @else
                        <div>
                            <strong>{{ $jornada['date']->format('d/m') }}</strong>
                            <span>{{ $jornada['count'] }} partido{{ $jornada['count'] === 1 ? '' : 's' }}</span>
                        </div>
                    @endif
                @empty
                    <div>
                        <strong>—</strong>
                        <span>No hay jornadas posteriores cargadas</span>
                    </div>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.stc>
