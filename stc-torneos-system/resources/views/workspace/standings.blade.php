<x-layouts.workspace
    :title="$title"
    heading="Clasificación y partidos"
    :subheading="$boardSubheading ?? ($tournament->name.' · '.$category->name)"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <p class="ws-ficha-back">
        <a href="{{ route('workspace.categories.home', $category) }}">← {{ $category->name }}</a>
    </p>

    @include('workspace.partials.competition-tabs', ['category' => $category, 'tab' => 'standings'])

    <section class="ws-standings ws-board">
        <div class="ws-standings-main">
            <div class="ws-board-toolbar">
                <label class="ws-board-phase">
                    <span class="ws-filter-label">
                        Fase
                        @if (! empty($canEdit))
                            <x-ws-pencil open="phases" label="Editar fases" />
                        @endif
                    </span>
                    <select onchange="location.href=this.value">
                        <option value="{{ route('workspace.categories.standings', [$category, 'phase' => 'all', 'round' => $selectedRound]) }}" @selected($selectedPhase === 'all')>Todas</option>
                        @foreach ($phases as $phase)
                            <option value="{{ route('workspace.categories.standings', [$category, 'phase' => $phase, 'round' => $selectedRound]) }}" @selected($selectedPhase === $phase)>{{ $phase }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="ws-board-hint">Clasificación por grupos</p>
                @if (! empty($canEdit))
                    <button type="button" class="ws-fab" data-ws-open="plus-menu" aria-label="Gestionar">+</button>
                @endif
            </div>

            @include('workspace.partials.standings-tables')
        </div>

        <div class="ws-board-side">
            <aside class="matches-panel">
                <header>
                    <h3>Partidos</h3>
                    <p class="ws-match-count">{{ $visibleMatches->count() }} de {{ $matches->count() }} partidos · <a href="{{ route('workspace.categories.fixture', $category) }}">Ver fixture</a></p>
                    <form class="match-panel-filter" method="get" action="{{ route('workspace.categories.standings', $category) }}">
                        <label>
                            <span>Fase</span>
                            <span class="match-filter-control">
                                <select name="phase" onchange="this.form.submit()" aria-label="Fase">
                                    <option value="all" @selected($selectedPhase === 'all')>Todas</option>
                                    @foreach ($phases as $phase)
                                        <option value="{{ $phase }}" @selected($selectedPhase === $phase)>{{ $phase }}</option>
                                    @endforeach
                                </select>
                                @if (! empty($canEdit))
                                    <x-ws-pencil open="phases" label="Editar fases" />
                                @endif
                            </span>
                        </label>
                        <label>
                            <span>Fecha</span>
                            <span class="match-filter-control">
                                <select name="round" onchange="this.form.submit()" aria-label="Fecha">
                                    <option value="all" @selected($selectedRound === 'all')>Todas</option>
                                    @foreach ($rounds as $round)
                                        <option value="{{ $round }}" @selected($selectedRound === $round)>{{ $round }}</option>
                                    @endforeach
                                </select>
                                @if (! empty($canEdit))
                                    <x-ws-pencil open="rounds" label="Editar fechas" />
                                @endif
                            </span>
                        </label>
                    </form>
                </header>

                <div class="ws-board-matches">
                    @forelse ($visibleMatches as $match)
                        @php
                            $when = $match->scheduled_at;
                            $weekdays = ['dom.', 'lun.', 'mar.', 'mié.', 'jue.', 'vie.', 'sáb.'];
                            $whenLabel = $when
                                ? $weekdays[$when->dayOfWeek].' '.$when->format('d/m/Y H:i')
                                : 'Sin horario';
                            $pen = $match->penaltyScore();
                            $showPen = ($pen['home'] ?? 0) > 0 || ($pen['away'] ?? 0) > 0;
                            $roundLabel = \App\Support\CategoryWorkspace::normalizeRound($match->round, $match->stage) ?: 'Sin fecha';
                        @endphp
                        <div class="match-mini-wrap">
                            <a class="match-mini-card{{ $match->isLive() ? ' is-live' : '' }}" href="{{ route('workspace.categories.matches.show', [$category, $match]) }}" title="Abrir partido">
                                <div class="ws-match-card-top">
                                    <x-ws-team-mark
                                        :team="$match->homeTeam"
                                        layout="stack"
                                        :zone="$match->teamGroupLabel($match->homeTeam)"
                                    />
                                    <div class="score-block">
                                        <span class="score">
                                            @if ($match->home_score === null && $match->away_score === null)
                                                - : -
                                            @else
                                                {{ $match->home_score ?? 0 }} : {{ $match->away_score ?? 0 }}
                                            @endif
                                        </span>
                                        <span class="match-status is-{{ $match->statusTone() }}">{{ $match->statusLabel() }}</span>
                                        @if ($showPen)
                                            <span class="ws-match-pen">{{ $pen['home'] }} x {{ $pen['away'] }}</span>
                                        @endif
                                    </div>
                                    <x-ws-team-mark
                                        :team="$match->awayTeam"
                                        layout="stack"
                                        :zone="$match->teamGroupLabel($match->awayTeam)"
                                    />
                                </div>
                                <div class="ws-match-card-meta">
                                    <em class="ws-match-round">{{ $roundLabel }}</em>
                                    @if ($match->zoneLabel())
                                        <em class="ws-match-zone">{{ $match->zoneLabel() }}</em>
                                    @endif
                                    @if ($match->field?->name)
                                        <strong>{{ $match->field->name }}</strong>
                                    @endif
                                    <small>{{ $whenLabel }}{{ ($match->sheet && ! empty($canViewPlanillas)) ? ' · Planilla' : '' }}</small>
                                </div>
                            </a>
                            <div class="ws-match-card-actions">
                                @if (! empty($canViewPlanillas))
                                    <a class="match-planilla-link" href="{{ route('workspace.categories.matches.planilla', [$category, $match]) }}" target="_blank" rel="noopener">Planilla PDF</a>
                                @endif
                                @include('workspace.partials.delete-match-form', ['match' => $match, 'class' => 'match-delete-form', 'buttonClass' => 'match-delete'])
                            </div>
                        </div>
                    @empty
                        <p class="ws-muted">No hay partidos en este recorte.</p>
                    @endforelse
                </div>

                <div class="ws-board-actions">
                    @if ($visibleMatches->isNotEmpty() && ! empty($canViewPlanillas))
                        <a class="stc-button ghost" href="{{ route('workspace.categories.planillas', array_filter(['category' => $category, 'phase' => $selectedPhase !== 'all' ? $selectedPhase : null, 'round' => $selectedRound !== 'all' ? $selectedRound : null])) }}">
                            Planillas en blanco
                        </a>
                    @endif
                    @if (! empty($canScheduleMatches))
                        <button type="button" class="stc-button ghost" data-ws-open="generate-fixture">Generar partidos</button>
                        <button type="button" class="stc-button ws-board-add" data-ws-open="add-match">Agregar partido</button>
                    @endif
                </div>
            </aside>

            <section class="date-stats-card">
                <h3>Estadísticas de la fecha</h3>
                <div class="date-stats-circles">
                    <span>Juegos <strong>{{ $dateStats['games'] }}</strong></span>
                    <span>Goles <strong>{{ $dateStats['goals'] }}</strong></span>
                </div>
                @foreach ($leaderboards as $label => $rows)
                    <h4 class="ws-rank-title">
                        <x-ws-event-icon :type="$label === 'Amarillas' ? 'yellow' : 'goal'" size="sm" />
                        {{ $label }}
                    </h4>
                    @forelse ($rows as $row)
                        <div class="date-player-row">
                            <img src="{{ $row[3] ?? asset('images/defaults/player.svg') }}" alt="{{ $row[0] }}" data-ws-rank-photo>
                            <span><strong>{{ $row[0] }}</strong><small>{{ $row[1] }}</small></span>
                            <b>{{ $row[2] }}</b>
                        </div>
                    @empty
                        <p class="ws-muted">Todavía no hay datos.</p>
                    @endforelse
                @endforeach
            </section>
        </div>
    </section>

    @if (! empty($canEdit))
        @include('workspace.partials.plus-menu', [
            'category' => $category,
            'phases' => $phases,
            'rounds' => $rounds,
            'selectedPhase' => $selectedPhase,
            'selectedRound' => $selectedRound,
            'matches' => $matches,
        ])
        @include('workspace.partials.config-modals')
        @include('workspace.partials.reorder-modals', [
            'category' => $category,
            'groups' => $groups,
            'visibleMatches' => $visibleMatches,
            'selectedPhase' => $selectedPhase,
            'selectedRound' => $selectedRound,
        ])
    @endif

    @if (! empty($canScheduleMatches))
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
                            <option value="{{ $phase }}" @selected($selectedPhase === $phase || ($selectedPhase === 'all' && $loop->first))>{{ $phase }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Fecha
                    <select name="round" required>
                        @foreach ($rounds as $round)
                            <option value="{{ $round }}" @selected($selectedRound === $round || ($selectedRound === 'all' && $round === 'Fecha 1'))>{{ $round }}</option>
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

        @include('workspace.partials.generate-fixture-modal')
        @include('workspace.partials.clear-fixture-modal')
    @endif
</x-layouts.workspace>
