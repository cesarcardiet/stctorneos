<x-layouts.workspace
    :title="$title"
    heading="Fixture"
    :subheading="$tournament->name.' · '.$category->name"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <section class="ws-kpis">
        @foreach ($stats as $stat)
            <article class="ws-kpi tone-{{ $stat['tone'] }}">
                <strong>{{ $stat['value'] }}</strong>
                <span>{{ $stat['label'] }}</span>
            </article>
        @endforeach
    </section>

    <form class="ws-toolbar ws-filter-bar" method="get" action="{{ route('workspace.categories.fixture', $category) }}">
        <label>Estado
            <select name="status" onchange="this.form.submit()">
                <option value="all" @selected($selectedStatus === 'all')>Todos</option>
                @foreach (\App\Models\FixtureMatch::operatorStatusLabels() as $value => $label)
                    <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="ws-filter-label">
                Fase
                @if (! empty($canEdit))
                    <x-ws-pencil open="phases" label="Editar fases" />
                @endif
            </span>
            <select name="phase" onchange="this.form.submit()">
                <option value="all" @selected($selectedPhase === 'all')>Todas</option>
                @foreach ($phases as $phase)
                    <option value="{{ $phase }}" @selected($selectedPhase === $phase)>{{ $phase }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="ws-filter-label">
                Fecha
                @if (! empty($canEdit))
                    <x-ws-pencil open="rounds" label="Editar fechas" />
                @endif
            </span>
            <select name="round" onchange="this.form.submit()">
                <option value="all" @selected($selectedRound === 'all')>Todas</option>
                @foreach ($rounds as $round)
                    <option value="{{ $round }}" @selected($selectedRound === $round)>{{ $round }}</option>
                @endforeach
            </select>
        </label>
        @if (! empty($canScheduleMatches))
            <div class="ws-row-actions">
                <button type="button" class="ws-btn" data-ws-open="generate-fixture">Generar partidos</button>
                <button type="submit" class="ws-btn ghost" form="fixture-publish">Publicar en app</button>
                <button type="button" class="ws-btn ghost" data-ws-open="add-match">Agregar partido</button>
                @if (($matches ?? collect())->isNotEmpty())
                    <button type="button" class="ws-btn ghost is-danger" data-ws-open="clear-fixture">Borrar todos los partidos</button>
                @endif
            </div>
        @endif
    </form>

    @if (! empty($canScheduleMatches))
        <form id="fixture-publish" method="post" action="{{ route('workspace.categories.fixture.publish', $category) }}">
            @csrf
            @method('PATCH')
        </form>
    @endif

    @if ($conflicts->isNotEmpty())
        <article class="ws-card ws-conflict">
            <h3>Conflictos detectados</h3>
            @foreach ($conflicts as $conflict)
                <p>{{ $conflict['label'] }} · {{ $conflict['match']->title() }} / {{ $conflict['other']->title() }}</p>
            @endforeach
        </article>
    @endif

    <article class="ws-card">
        <header class="ws-card-head">
            <h3>Agenda de partidos</h3>
            <a href="{{ route('workspace.categories.standings', $category) }}">Ver clasificación</a>
        </header>

        <div class="ws-fixture-table">
            @forelse ($visibleMatches->groupBy(fn ($match) => \App\Support\CategoryWorkspace::normalizeRound($match->round, $match->stage) ?: 'Sin fecha') as $roundName => $roundMatches)
                <section class="ws-fixture-date">
                    <h4>{{ $roundName }}</h4>
                    @foreach ($roundMatches as $match)
                        <div @class(['ws-fixture-row', 'is-live' => $match->status === 'live', 'has-conflict' => $conflictIds->contains($match->id)])>
                            <div class="ws-fixture-match">
                                <div class="ws-fixture-sides">
                                    <x-ws-team-mark :team="$match->homeTeam" />
                                    <b class="ws-fixture-score">
                                        @if ($match->home_score === null && $match->away_score === null)
                                            vs
                                        @else
                                            {{ $match->home_score }} : {{ $match->away_score }}
                                        @endif
                                    </b>
                                    <x-ws-team-mark :team="$match->awayTeam" />
                                </div>
                                <div class="ws-fixture-tags">
                                    @if ($match->zoneLabel())
                                        <em @class(['ws-fixture-tag', 'is-inter' => $match->isInterzonal()])>{{ $match->zoneLabel() }}</em>
                                    @endif
                                    <em class="ws-fixture-tag">{{ \App\Support\CategoryWorkspace::matchPhase($match, $phases) }}</em>
                                </div>
                            </div>
                            <span class="ws-row-actions">
                                <a href="{{ route('workspace.categories.matches.show', [$category, $match]) }}">Ver partido</a>
                                @if (! empty($canViewPlanillas))
                                    <a href="{{ route('workspace.categories.matches.planilla', [$category, $match]) }}" target="_blank" rel="noopener">Planilla</a>
                                @endif
                                @if (! empty($canScheduleMatches) && ! $match->published)
                                    <form method="post" action="{{ route('workspace.categories.matches.publish', [$category, $match]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit">Publicar</button>
                                    </form>
                                @endif
                                @if (! empty($canScheduleMatches) && $match->published)
                                    <form method="post" action="{{ route('workspace.categories.matches.observe', [$category, $match]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit">Observar</button>
                                    </form>
                                @endif
                                @include('workspace.partials.delete-match-form', ['match' => $match])
                            </span>
                            <div class="ws-fixture-meta">
                                <span>{{ $match->scheduled_at?->format('d/m H:i') ?: 'Sin horario' }}</span>
                                <span>{{ $match->field?->name ?: 'Sin cancha' }}</span>
                                <span>
                                    @if ($match->isLive())
                                        <span class="match-live-badge">En juego</span>
                                    @else
                                        {{ $match->statusLabel() }}
                                    @endif
                                    @if ($conflictIds->contains($match->id))
                                        · Conflicto
                                    @endif
                                </span>
                                @if (! empty($canViewPlanillas))
                                    <span>{{ $match->published ? 'En app' : 'Borrador' }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </section>
            @empty
                <p class="ws-muted">No hay partidos con esos filtros. Generá el fixture o programá un cruce.</p>
            @endforelse
        </div>

        @if ($visibleMatches->isNotEmpty() && ! empty($canViewPlanillas))
            <p class="ws-planillas-bulk no-print">
                <a class="ws-btn ghost" href="{{ route('workspace.categories.planillas', array_filter(['category' => $category, 'phase' => $selectedPhase !== 'all' ? $selectedPhase : null, 'round' => $selectedRound !== 'all' ? $selectedRound : null])) }}">
                    Planillas en blanco
                </a>
            </p>
        @endif
    </article>

    @if (! empty($canScheduleMatches))
        @include('workspace.partials.generate-fixture-modal')
        @include('workspace.partials.clear-fixture-modal')

        <x-ws-modal id="add-match" title="Agregar partido" :wide="true">
            <x-ws-form :action="route('workspace.categories.matches.store', $category)" submit="Agregar">
                <label>Local
                    <select name="home_team_id" required>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Visitante
                    <select name="away_team_id" required>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Cancha
                    <select name="field_id" required>
                        @foreach ($fields as $field)
                            <option value="{{ $field->id }}">{{ $field->name }}{{ $field->venue?->name ? ' · '.$field->venue->name : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ws-datetime name="scheduled_at" label="Día y hora" :value="$defaultStart ?? null" />
                <label>Fase
                    <select name="stage" required>
                        @foreach ($phases as $phase)
                            <option value="{{ $phase }}" @selected($loop->first)>{{ $phase }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Fecha
                    <select name="round" required>
                        @foreach ($rounds as $round)
                            <option value="{{ $round }}" @selected($round === 'Fecha 1')>{{ $round }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ws-check">
                    <input type="hidden" name="return_leg" value="0">
                    <input type="checkbox" name="return_leg" value="1">
                    Crear también la vuelta
                </label>
            </x-ws-form>
        </x-ws-modal>
    @endif

    @if (! empty($canEdit))
        @include('workspace.partials.config-modals')
    @endif
</x-layouts.workspace>
