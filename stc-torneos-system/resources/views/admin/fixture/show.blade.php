<x-layouts.stc
    title="Detalle partido | STC Torneos"
    active="Fixture"
    :heading="$match->title()"
    :subheading="'Detalle Partido Admin · '.($match->category?->name ?? 'Sin categoría').' · '.($match->round ?: $match->stage ?: $match->statusLabel())"
    :heading-left="$match->homeTeam?->shieldUrl()"
    :heading-right="$match->awayTeam?->shieldUrl()"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @if ($conflicts->isNotEmpty())
        <div class="system-alert">Este partido tiene conflictos de programación. Revisá cancha, equipos o árbitro.</div>
    @endif

    <a class="back-link" href="{{ route('admin.fixture.index') }}">← Volver a la agenda</a>

    @php
        $events = $match->sheet?->events ?? collect();
        $starterIds = $match->lineups->where('starter', true)->pluck('player_id')->all();
        $homePlayers = ($match->homeTeam?->players ?? collect())->sortBy('last_name');
        $awayPlayers = ($match->awayTeam?->players ?? collect())->sortBy('last_name');
        $penalty = $match->penaltyScore();
    @endphp

    <div class="category-actions fixture-toolbar fixture-match-toolbar">
        <form method="post" action="{{ route('admin.fixture.status', $match) }}">
            @csrf
            @method('PATCH')
            <button type="submit" name="status" value="paused">Pausar</button>
        </form>
        <form method="post" action="{{ route('admin.fixture.status', $match) }}">
            @csrf
            @method('PATCH')
            <button type="submit" name="status" value="live">En juego</button>
        </form>
        <form method="post" action="{{ route('admin.fixture.status', $match) }}">
            @csrf
            @method('PATCH')
            <button type="submit" name="status" value="finished">Finalizar</button>
        </form>
        <form method="post" action="{{ route('admin.fixture.status', $match) }}">
            @csrf
            @method('PATCH')
            <button type="submit" name="status" value="suspended">Suspender</button>
        </form>
        <form method="post" action="{{ route('admin.fixture.status', $match) }}">
            @csrf
            @method('PATCH')
            <button type="submit" name="status" value="validated">Validar</button>
        </form>
        <a href="{{ route('admin.fixture.edit', $match) }}">Editar programación</a>
        <form method="post" action="{{ route('admin.fixture.status', $match) }}">
            @csrf
            @method('PATCH')
            <button type="submit" name="status" value="rescheduled">Reprogramar</button>
        </form>
        @if ($match->published)
            <form method="post" action="{{ route('admin.fixture.observe', $match) }}">
                @csrf
                @method('PATCH')
                <button type="submit">Observar resultado</button>
            </form>
        @endif
        @unless ($match->published)
            <form method="post" action="{{ route('admin.fixture.publish.match', $match) }}">
                @csrf
                @method('PATCH')
                <button type="submit">Publicar en app</button>
            </form>
        @endunless
        @if ($match->sheet && auth()->user()?->hasPermission('match_sheets.manage'))
            <a href="{{ route('admin.sheets.show', $match->sheet) }}">Ver planilla</a>
        @endif
    </div>

    @if (in_array($match->status, ['finished', 'validated'], true))
        <form class="fixture-reopen-bar" method="post" action="{{ route('admin.fixture.reopen', $match) }}" data-confirm="¿Reabrir el partido oficial? La planilla se desbloquea y el resultado deja de publicarse.">
            @csrf
            @method('PATCH')
            <input type="text" name="reason" required minlength="8" placeholder="Motivo de la reapertura">
            <button type="submit">Reabrir partido</button>
        </form>
    @endif

    <section class="fixture-scoreboard admin-figma-panel">
        <div class="fixture-score-teams">
            <div>
                <img src="{{ $match->homeTeam?->shieldUrl() ?? asset('images/stc-logo.png') }}" alt="{{ $match->homeTeam?->name }}">
                @if ($match->homeTeam)
                    <a class="row-title-link" href="{{ route('admin.teams.show', $match->homeTeam) }}"><strong>{{ $match->homeTeam->name }}</strong></a>
                    <a class="fixture-team-link" href="{{ route('admin.teams.show', $match->homeTeam) }}">Ver equipo</a>
                @else
                    <strong>Local</strong>
                @endif
            </div>
            <span>{{ $match->scoreLine() }}</span>
            <div>
                <img src="{{ $match->awayTeam?->shieldUrl() ?? asset('images/stc-logo.png') }}" alt="{{ $match->awayTeam?->name }}">
                @if ($match->awayTeam)
                    <a class="row-title-link" href="{{ route('admin.teams.show', $match->awayTeam) }}"><strong>{{ $match->awayTeam->name }}</strong></a>
                    <a class="fixture-team-link" href="{{ route('admin.teams.show', $match->awayTeam) }}">Ver equipo</a>
                @else
                    <strong>Visitante</strong>
                @endif
            </div>
        </div>
        <p class="fixture-score-meta">
            <span class="admin-status status-{{ $match->status }}">{{ $match->statusLabel() }}</span>
            @if ($match->minute) <span>{{ $match->minute }}</span> @endif
            <span>{{ $match->scheduled_at?->format('d/m/Y H:i') }}</span>
            @if ($match->field)
                <span><a href="{{ route('admin.fields.show', $match->field) }}">{{ $match->field->name }}</a></span>
            @endif
            <span>{{ $match->field?->venue?->name }}</span>
        </p>
    </section>

    <section class="fixture-match-grid">
        <article class="admin-figma-panel admin-figma-form">
            <header class="admin-list-heading">
                <h3>Resultado y operación</h3>
            </header>
            <form method="post" action="{{ route('admin.fixture.status', $match) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $match->status }}">
                <div class="ficha-row">
                    <label>Goles local <input type="number" name="home_score" value="{{ $match->home_score }}"></label>
                    <label>Goles visitante <input type="number" name="away_score" value="{{ $match->away_score }}"></label>
                    <label>Minuto <input name="minute" value="{{ $match->minute }}" placeholder="31:42"></label>
                    <label>Período
                        <select name="period">
                            <option value="">Sin período</option>
                            <option value="1t" @selected($match->period === '1t')>Primer tiempo</option>
                            <option value="2t" @selected($match->period === '2t')>Segundo tiempo</option>
                            <option value="et" @selected($match->period === 'et')>Alargue</option>
                            <option value="pen" @selected($match->period === 'pen')>Penales</option>
                        </select>
                    </label>
                </div>
                <div class="ficha-row">
                    <label>Árbitro
                        <select name="referee_user_id">
                            <option value="">Sin asignar</option>
                            @foreach ($officials as $official)
                                <option value="{{ $official->id }}" @selected((int) $match->referee_user_id === $official->id)>{{ $official->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Asistente
                        <select name="assistant_user_id">
                            <option value="">Sin asignar</option>
                            @foreach ($officials as $official)
                                <option value="{{ $official->id }}" @selected((int) $match->assistant_user_id === $official->id)>{{ $official->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Responsable de carga
                        <select name="scorer_user_id">
                            <option value="">Mesa</option>
                            @foreach ($officials as $official)
                                <option value="{{ $official->id }}" @selected((int) $match->scorer_user_id === $official->id)>{{ $official->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <button class="ficha-save" type="submit">Guardar supervisión</button>
            </form>
        </article>

        <aside class="admin-figma-panel">
            <header class="admin-list-heading">
                <h3>Datos del partido</h3>
            </header>
            <div class="stc-table admin-figma-table fixture-info-table">
                <div class="table-head"><span>Campo</span><span>Dato</span></div>
                <div class="table-row"><span>Torneo</span><span>
                    @if ($match->tournament)
                        <a class="row-title-link" href="{{ route('admin.tournaments.show', $match->tournament) }}">{{ $match->tournament->name }}</a>
                    @else
                        —
                    @endif
                </span></div>
                <div class="table-row"><span>Categoría</span><span>
                    @if ($match->category)
                        <a class="row-title-link" href="{{ route('admin.categories.show', $match->category) }}">{{ $match->category->name }}</a>
                    @else
                        —
                    @endif
                </span></div>
                <div class="table-row"><span>Local</span><span>
                    @if ($match->homeTeam)
                        <x-entity-ref :href="route('admin.teams.show', $match->homeTeam)" :src="$match->homeTeam->shieldUrl()" :alt="$match->homeTeam->name">
                            {{ $match->homeTeam->name }} · {{ $homePlayers->count() }} jugadores
                        </x-entity-ref>
                    @endif
                </span></div>
                <div class="table-row"><span>Visitante</span><span>
                    @if ($match->awayTeam)
                        <x-entity-ref :href="route('admin.teams.show', $match->awayTeam)" :src="$match->awayTeam->shieldUrl()" :alt="$match->awayTeam->name">
                            {{ $match->awayTeam->name }} · {{ $awayPlayers->count() }} jugadores
                        </x-entity-ref>
                    @endif
                </span></div>
                <div class="table-row"><span>Publicación</span><span>{{ $match->published ? 'En app' : 'Borrador' }}</span></div>
                <div class="table-row"><span>Árbitro</span><span>{{ $match->refereeLabel() }}</span></div>
                <div class="table-row"><span>Asistente</span><span>{{ $match->assistantLabel() }}</span></div>
                <div class="table-row"><span>Carga</span><span>{{ $match->scorerUser?->name ?: ($match->sheet?->responsibleLabel() ?: 'Mesa') }}</span></div>
                <div class="table-row"><span>Período</span><span>{{ $match->periodLabel() }}</span></div>
                @if ($winner = $match->winnerTeam())
                    <div class="table-row"><span>Ganador</span><span>{{ $winner->name }}</span></div>
                @endif
                @if ($match->nextMatch)
                    <div class="table-row"><span>Pasa a</span><span>{{ $match->nextMatch->title() }} ({{ $match->next_slot === 'away' ? 'visitante' : 'local' }})</span></div>
                @endif
                @if ($match->reopen_reason)
                    <div class="table-row"><span>Reapertura</span><span>{{ $match->reopen_reason }}</span></div>
                @endif
                @if ($match->sheet)
                    <div class="table-row"><span>Planilla</span><span>{{ $match->sheet->statusLabel() }}</span></div>
                @endif
            </div>
        </aside>
    </section>

    <section class="admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Eventos en vivo</h3>
        </header>
        <div class="stc-table admin-figma-table fixture-events-table">
            <div class="table-head">
                <span>Min</span>
                <span>Tipo</span>
                <span>Jugador</span>
                <span>Equipo</span>
                <span>Detalle</span>
            </div>
            @forelse ($events as $event)
                <div class="table-row">
                    <span>{{ $event->minuteLabel() }}</span>
                    <span>{{ $event->typeLabel() }}</span>
                    <span>
                        @if ($event->player)
                            <x-entity-cell
                                :href="route('admin.players.show', $event->player)"
                                :src="$event->actorPhotoUrl()"
                                :alt="$event->actorName()"
                                shape="round"
                            >
                                {{ $event->actorName() }}
                            </x-entity-cell>
                        @else
                            <x-entity-cell :src="$event->actorPhotoUrl()" :alt="$event->actorName()" shape="round">
                                {{ $event->actorName() }}
                            </x-entity-cell>
                        @endif
                    </span>
                    <span>
                        @if ($event->team)
                            <x-entity-ref :href="route('admin.teams.show', $event->team)" :src="$event->team->shieldUrl()" :alt="$event->team->name">
                                {{ $event->team->name }}
                            </x-entity-ref>
                        @else
                            —
                        @endif
                    </span>
                    <span>{{ $event->detail ?: '—' }}</span>
                </div>
            @empty
                <div class="table-row">
                    <span>—</span>
                    <span>Sin eventos</span>
                    <span>Todavía no hay eventos de planilla. La supervisión usa el marcador y el período de acá.</span>
                    <span>—</span>
                    <span>—</span>
                </div>
            @endforelse
        </div>
    </section>

    <section class="admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Serie de penales {{ $penalty['home'] }} - {{ $penalty['away'] }}</h3>
        </header>
        @if ($winner = $match->penaltyWinnerId())
            <p class="fixture-panel-copy">Ganador de la serie: <strong>{{ $winner === $match->home_team_id ? $match->homeTeam?->name : $match->awayTeam?->name }}</strong>@if ($match->isKnockoutStage()) · define el cruce @endif</p>
        @elseif ($match->isKnockoutStage() && $match->home_score !== null && (int) $match->home_score === (int) $match->away_score)
            <p class="fixture-panel-copy">Empate en knockout: la serie de penales define al ganador del cruce.</p>
        @endif
        <div class="stc-table admin-figma-table fixture-penalty-table">
            <div class="table-head">
                <span>#</span>
                <span>Equipo</span>
                <span>Ejecutante</span>
                <span>Resultado</span>
                <span>Acción</span>
            </div>
            @forelse ($match->penaltyKicks as $kick)
                <div class="table-row">
                    <span>{{ $kick->sequence }}</span>
                    <span>
                        @if ($kick->team)
                            <x-entity-ref :href="route('admin.teams.show', $kick->team)" :src="$kick->team->shieldUrl()" :alt="$kick->team->name">
                                {{ $kick->team->name }}
                            </x-entity-ref>
                        @else
                            —
                        @endif
                    </span>
                    <span>
                        @if ($kick->player)
                            <x-entity-ref :href="route('admin.players.show', $kick->player)" :src="$kick->player->listPhotoUrl()" :alt="$kick->player->fullName()" shape="round">
                                {{ $kick->player->fullName() }}
                            </x-entity-ref>
                        @else
                            Sin jugador
                        @endif
                    </span>
                    <span>{{ $kick->resultLabel() }}</span>
                    <span class="category-actions">
                        <form method="post" action="{{ route('admin.fixture.penalties.destroy', [$match, $kick]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="danger" type="submit">Quitar</button>
                        </form>
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span>—</span>
                    <span>Sin tiros cargados</span>
                    <span>—</span>
                    <span>—</span>
                    <span>—</span>
                </div>
            @endforelse
        </div>
        <form class="admin-figma-form fixture-inline-form" method="post" action="{{ route('admin.fixture.penalties.store', $match) }}">
            @csrf
            <div class="ficha-row">
                <label>Equipo
                    <select name="team_id" required>
                        <option value="{{ $match->home_team_id }}">{{ $match->homeTeam?->name }}</option>
                        <option value="{{ $match->away_team_id }}">{{ $match->awayTeam?->name }}</option>
                    </select>
                </label>
                <label>Ejecutante
                    <select name="player_id">
                        <option value="">Sin jugador</option>
                        @foreach ($homePlayers->concat($awayPlayers) as $player)
                            <option value="{{ $player->id }}">{{ $player->fullName() }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Resultado
                    <select name="scored">
                        <option value="1">Gol</option>
                        <option value="0">Errado</option>
                    </select>
                </label>
            </div>
            <button class="ficha-save" type="submit">Cargar tiro</button>
        </form>
    </section>

    <section class="admin-figma-panel">
        <header class="admin-list-heading">
            <h3>XI titular</h3>
        </header>
        <p class="fixture-panel-copy">Marcá los titulares de cada equipo. El resto queda como suplente.</p>
        <form method="post" action="{{ route('admin.fixture.lineups.store', $match) }}">
            @csrf
            <div class="fixture-lineup-grid">
                @foreach ([['team' => $match->homeTeam, 'players' => $homePlayers], ['team' => $match->awayTeam, 'players' => $awayPlayers]] as $side)
                    <div>
                        <div class="fixture-lineup-team">
                            @if ($side['team'])
                                <x-entity-ref :href="route('admin.teams.show', $side['team'])" :src="$side['team']->shieldUrl()" :alt="$side['team']->name">
                                    {{ $side['team']->name }}
                                </x-entity-ref>
                            @endif
                        </div>
                        <div class="stc-table admin-figma-table fixture-lineup-table">
                        <div class="table-head">
                            <span>Jugador</span>
                            <span>N°</span>
                            <span>Titular</span>
                        </div>
                        @forelse ($side['players'] as $player)
                            <div class="table-row fixture-lineup-row">
                                <span>
                                    <x-entity-cell
                                        :href="route('admin.players.show', $player)"
                                        :src="$player->listPhotoUrl()"
                                        :alt="$player->fullName()"
                                        shape="round"
                                    >
                                        {{ $player->fullName() }}
                                    </x-entity-cell>
                                </span>
                                <span>{{ $player->jersey_number ?: '—' }}</span>
                                <span>
                                    <label class="fixture-starter-check">
                                        <input type="checkbox" name="starters[]" value="{{ $player->id }}" @checked(in_array($player->id, $starterIds, true))>
                                        Titular
                                    </label>
                                </span>
                            </div>
                        @empty
                            <div class="table-row">
                                <span>Sin jugadores cargados</span>
                                <span>—</span>
                                <span>—</span>
                            </div>
                        @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="admin-figma-form-actions">
                <button class="ficha-save" type="submit">Guardar titulares</button>
            </div>
        </form>
    </section>

    @if ($conflicts->isNotEmpty())
        <section class="admin-figma-panel">
            <header class="admin-list-heading">
                <h3>Conflictos</h3>
            </header>
            <div class="stc-table admin-figma-table fixture-conflict-table">
                <div class="table-head">
                    <span>Conflicto</span>
                    <span>Partido</span>
                    <span>Acción</span>
                </div>
                @foreach ($conflicts as $conflict)
                    @php $other = $conflict['other']->id === $match->id ? $conflict['match'] : $conflict['other']; @endphp
                    <div class="table-row">
                        <span>{{ $conflict['label'] }}</span>
                        <span>{{ $other->title() }}</span>
                        <span class="category-actions">
                            <a href="{{ route('admin.fixture.edit', $other) }}">Resolver</a>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <form class="category-actions" method="post" action="{{ route('admin.fixture.destroy', $match) }}" data-confirm="¿Eliminar este partido del fixture?">
        @csrf
        @method('DELETE')
        <button class="danger" type="submit">Eliminar partido</button>
    </form>
</x-layouts.stc>
