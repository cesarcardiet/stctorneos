<x-layouts.workspace
    :title="$title"
    heading="Partido"
    :subheading="$match->homeTeam?->name.' vs '.$match->awayTeam?->name"
    :category="$category"
    :tournament="$tournament"
    active="Clasificación"
>
    <p class="ws-back">
        <a href="{{ route('workspace.categories.fixture', $category) }}">← Fixture</a>
        <a href="{{ route('workspace.categories.standings', $category) }}">Clasificación</a>
        @if (! empty($canViewPlanillas))
            <a href="{{ route('workspace.categories.matches.planilla', [$category, $match]) }}" target="_blank" rel="noopener">Planilla en blanco</a>
        @endif
    </p>

    <section class="ws-ficha">
        <article @class(['ws-card', 'is-live-match' => $match->isLive()])>
            <p class="stc-eyebrow">
                @if ($match->zoneLabel())
                    {{ $match->zoneLabel() }} ·
                @endif
                <span data-ws-status-text>{{ $match->statusLabel() }}</span>
                · <span data-ws-field-text>{{ $match->field?->name }}</span>
                · <span data-ws-when-text>{{ $match->scheduled_at?->format('d/m H:i') }}</span>
            </p>
            <div class="ws-scoreboard">
                <div class="ws-score-team">
                    <x-ws-team-mark
                        :team="$match->homeTeam"
                        layout="stack"
                        :zone="$match->teamGroupLabel($match->homeTeam)"
                    />
                </div>
                <div class="score-block">
                    <b data-ws-score-text>{{ $sheet?->home_score ?? $match->home_score ?? 0 }} : {{ $sheet?->away_score ?? $match->away_score ?? 0 }}</b>
                    <span class="match-status is-{{ $match->statusTone() }}" data-ws-status-banner>{{ $match->statusLabel() }}</span>
                </div>
                <div class="ws-score-team">
                    <x-ws-team-mark
                        :team="$match->awayTeam"
                        layout="stack"
                        :zone="$match->teamGroupLabel($match->awayTeam)"
                    />
                </div>
            </div>
            @if ($canScheduleMatches)
                <form class="ws-schedule-form" method="post" action="{{ route('workspace.categories.matches.schedule', [$category, $match]) }}" data-ws-autosave>
                    @csrf
                    @method('PATCH')
                    <label>Cancha
                        <select name="field_id" required>
                            @foreach ($fields as $field)
                                <option value="{{ $field->id }}" @selected((int) $match->field_id === (int) $field->id)>{{ $field->name }}{{ $field->venue?->name ? ' · '.$field->venue->name : '' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-ws-datetime name="scheduled_at" label="Día y hora" :value="$match->scheduled_at" />
                    <button type="submit" class="ws-btn">Guardar horario</button>
                </form>
            @endif
            @if ($canOperateMatch)
                <form class="ws-schedule-form" method="post" action="{{ route('workspace.categories.matches.result', [$category, $match]) }}" data-ws-autosave>
                    @csrf
                    @method('PATCH')
                    <label>Goles local
                        <input type="number" name="home_score" min="0" max="30" value="{{ $match->home_score ?? 0 }}" required>
                    </label>
                    <label>Goles visitante
                        <input type="number" name="away_score" min="0" max="30" value="{{ $match->away_score ?? 0 }}" required>
                    </label>
                    <label>Estado
                        <select name="status" required data-ws-status-select>
                            @foreach (\App\Models\FixtureMatch::operatorStatusLabels() as $value => $label)
                                <option value="{{ $value }}" @selected($match->statusOption() === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="ws-btn">Guardar resultado</button>
                </form>
            @endif
            @include('workspace.partials.delete-match-form', ['match' => $match, 'buttonClass' => 'ws-btn danger'])
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">
                @if (! empty($canViewPlanillas))
                    Planilla {{ $sheet ? '· '.$sheet->statusLabel() : '' }}
                @else
                    Resumen del partido
                @endif
            </p>
            @if ($canOperateMatch && ! ($sheet?->locked))
                <p class="ws-muted ws-planilla-hint">Jugadas del partido y penalizaciones Fair Play (padres, cuerpo técnico, puntualidad) impactan en Rankings y en la tabla de disciplina.</p>
                <form class="ws-inline-add ws-planilla-events" method="post" action="{{ route('workspace.categories.matches.events', [$category, $match]) }}">
                    @csrf
                    <select name="type" required data-ws-event-type>
                        @foreach (['goal' => 'Gol', 'assist' => 'Asistencia', 'yellow' => 'Amarilla', 'red' => 'Roja', 'substitution' => 'Cambio'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="actor_kind" data-ws-actor-kind>
                        <option value="player">Jugador</option>
                        <option value="staff">Cuerpo técnico</option>
                    </select>
                    <select name="team_id" required data-ws-team-filter>
                        <option value="{{ $match->home_team_id }}">{{ $match->homeTeam?->name }}</option>
                        <option value="{{ $match->away_team_id }}">{{ $match->awayTeam?->name }}</option>
                    </select>
                    <select name="player_id" data-ws-player-filter data-ws-actor-field="player">
                        <option value="">Sin jugador</option>
                        @foreach ($match->homeTeam?->players ?? [] as $player)
                            <option value="{{ $player->id }}" data-team-id="{{ $match->home_team_id }}">{{ $player->fullName() }}</option>
                        @endforeach
                        @foreach ($match->awayTeam?->players ?? [] as $player)
                            <option value="{{ $player->id }}" data-team-id="{{ $match->away_team_id }}">{{ $player->fullName() }}</option>
                        @endforeach
                    </select>
                    <select name="team_staff_id" data-ws-staff-filter data-ws-actor-field="staff" hidden>
                        <option value="">Sin integrante</option>
                        @foreach ($match->homeTeam?->staffMembers ?? [] as $member)
                            <option value="{{ $member->id }}" data-team-id="{{ $match->home_team_id }}">{{ $member->fullName() }} · {{ $member->roleLabel() }}</option>
                        @endforeach
                        @foreach ($match->awayTeam?->staffMembers ?? [] as $member)
                            <option value="{{ $member->id }}" data-team-id="{{ $match->away_team_id }}">{{ $member->fullName() }} · {{ $member->roleLabel() }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="minute" min="0" max="130" placeholder="Min">
                    <button type="submit" class="ws-btn">Cargar jugada</button>
                </form>

                <form class="ws-inline-add ws-planilla-incidents" method="post" action="{{ route('workspace.categories.matches.incidents', [$category, $match]) }}">
                    @csrf
                    <select name="fair_play_kind" required>
                        <optgroup label="Padres / familiares">
                            @foreach (['family_misconduct' => 'Conducta inapropiada padre / familiar', 'family_expulsion' => 'Expulsión o conducta grave padre / familiar'] as $value => $label)
                                <option value="{{ $value }}" @selected($value === 'family_misconduct')>{{ $label }} (+{{ \App\Support\FairPlayRules::scale()[$value] }})</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Otros">
                            @foreach (['adult_serious' => 'Incidente grave protagonizado por adulto', 'late_arrival' => 'Llegada tarde de la delegación'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }} (+{{ \App\Support\FairPlayRules::scale()[$value] }})</option>
                            @endforeach
                        </optgroup>
                    </select>
                    <select name="team_id" required>
                        <option value="">Equipo sancionado</option>
                        <option value="{{ $match->home_team_id }}">{{ $match->homeTeam?->name }}</option>
                        <option value="{{ $match->away_team_id }}">{{ $match->awayTeam?->name }}</option>
                    </select>
                    <input type="text" name="title" placeholder="Detalle, ej: padre del N° 9">
                    <input type="text" name="moment" placeholder="Min / momento">
                    <button type="submit" class="ws-btn">Cargar penalización</button>
                </form>
            @endif

            <h3 class="ws-plays-title">Jugadas del partido</h3>

            @if (($sheet?->events ?? collect())->isNotEmpty())
                <div class="ws-plays-summary" aria-label="Resumen de jugadas">
                    @foreach ($sheet->events as $event)
                        <span @class(['ws-plays-chip', 'is-'.$event->tone()]) title="{{ $event->headline() }} · {{ $event->actorName() }}">
                            <x-ws-event-icon :type="$event->iconType()" size="xs" />
                        </span>
                    @endforeach
                </div>
            @endif

            <ul class="ws-plays-list">
                @forelse ($sheet?->events ?? [] as $event)
                    <li @class([
                        'ws-play-card',
                        'is-'.$event->tone(),
                        $event->isHomeSide($match->home_team_id) ? 'is-home' : 'is-away',
                    ])>
                        <img
                            class="ws-event-photo"
                            src="{{ $event->actorPhotoUrl() }}"
                            alt="{{ $event->actorName() }}"
                            data-ws-actor-photo
                            width="44"
                            height="44"
                        >
                        <div class="ws-play-copy">
                            <strong>{{ $event->headline() }}</strong>
                            <span>
                                @if ($event->actorSubtitle())
                                    {{ $event->actorSubtitle() }} ·
                                @endif
                                {{ $event->actorName() }}
                                @if ($event->isStaffEvent())
                                    <em class="ws-play-staff-tag">Cuerpo técnico</em>
                                @endif
                            </span>
                            <small>{{ $event->minuteLabel() }}</small>
                        </div>
                        <x-ws-event-icon :type="$event->iconType()" />
                    </li>
                @empty
                    <li class="ws-muted">{{ ! empty($canViewPlanillas) ? 'Todavía no hay eventos en la planilla.' : 'Todavía no hay goles, tarjetas ni cambios registrados.' }}</li>
                @endforelse
            </ul>

            <h3 class="ws-plays-title">Penalizaciones Fair Play</h3>
            <ul class="ws-plays-list ws-incidents-list">
                @forelse ($sheet?->incidents ?? [] as $incident)
                    <li class="ws-play-card is-fairplay">
                        <div class="ws-play-copy">
                            <strong>{{ $incident->headline() }}</strong>
                            <span>{{ $incident->related_name ?: 'Sin equipo' }}</span>
                            <small>{{ $incident->fairPlayKindLabel() ?: 'Fair Play' }} · +{{ $incident->fairPlayPoints($category) }} pts</small>
                            @if ($incident->notes)
                                <small>{{ $incident->notes }}</small>
                            @endif
                        </div>
                        @if ($canOperateMatch && ! ($sheet?->locked))
                            <form method="post" action="{{ route('workspace.categories.matches.incidents.destroy', [$category, $match, $incident]) }}" data-confirm="¿Eliminar esta penalización?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ws-btn ghost danger">Eliminar</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="ws-muted">Todavía no hay penalizaciones de padres ni puntualidad.</li>
                @endforelse
            </ul>

            @if ($canOperateMatch && ! ($sheet?->locked))
                <form method="post" action="{{ route('workspace.categories.matches.close', [$category, $match]) }}">
                    @csrf
                    <button type="submit" class="stc-button full">Cerrar planilla y publicar</button>
                </form>
            @elseif ($canOperateMatch && $sheet?->locked)
                <p class="ws-muted">La planilla está cerrada. Reabrí el partido para corregir goles o eventos.</p>
                <form method="post" action="{{ route('workspace.categories.matches.reopen', [$category, $match]) }}">
                    @csrf
                    <button type="submit" class="stc-button full">Reabrir partido</button>
                </form>
            @endif
        </article>
    </section>
</x-layouts.workspace>
