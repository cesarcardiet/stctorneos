@php
    $homeScore = $sheet?->home_score ?? $match->home_score ?? 0;
    $awayScore = $sheet?->away_score ?? $match->away_score ?? 0;
    $lastEvent = $sheet?->events?->sortByDesc('id')->first();
    $liveSides = collect([$match->homeTeam, $match->awayTeam])->filter()->map(function ($team) use ($match) {
        $rows = $match->lineups->where('team_id', $team->id)->sortBy(
            fn ($row) => sprintf('%d-%04d', $row->starter ? 0 : 1, (int) $row->sort_order)
        );
        $players = $rows->isNotEmpty()
            ? $rows->map(function ($row) {
                $player = $row->player;
                if (! $player) {
                    return null;
                }

                return [
                    'id' => $player->id,
                    'name' => $player->fullName(),
                    'shirt' => $row->shirt_number ?: $player->jersey_number,
                    'starter' => (bool) $row->starter,
                ];
            })->filter()->values()
            : $team->players->map(fn ($player) => [
                'id' => $player->id,
                'name' => $player->fullName(),
                'shirt' => $player->jersey_number,
                'starter' => false,
            ])->values();

        return [
            'team' => $team,
            'players' => $players,
            'staff' => $team->staffMembers->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->fullName(),
                'role' => $member->roleLabel(),
            ])->values(),
        ];
    });
    $incidentKinds = [
        'family_misconduct' => 'Conducta de un padre',
        'family_expulsion' => 'Expulsión de un padre',
        'adult_serious' => 'Incidente de un adulto',
        'late_arrival' => 'Llegada tarde',
    ];
@endphp

<section
    class="lv-panel"
    data-live-panel
    data-match-id="{{ $match->id }}"
    data-status="{{ $match->statusOption() }}"
>
    <header class="lv-top">
        <strong class="lv-title">Partido en vivo</strong>
        <span @class(['lv-chip', 'is-'.$match->statusTone()])>
            <i data-live-dot></i>
            @if ($match->isLive())
                En juego
            @elseif ($match->statusOption() === 'paused')
                Pausado
            @else
                Por iniciar
            @endif
            · <b data-live-clock>00:00</b>
        </span>
    </header>

    <p class="lv-meta">{{ $category->name }}@if ($match->field?->name) · {{ $match->field->name }}@endif</p>

    <div class="lv-score">
        <div>
            <img src="{{ $match->homeTeam?->shieldUrl() }}" alt="">
            <strong>{{ $match->homeTeam?->name }}</strong>
        </div>
        <b data-ws-score-text>{{ $homeScore }} - {{ $awayScore }}</b>
        <div>
            <img src="{{ $match->awayTeam?->shieldUrl() }}" alt="">
            <strong>{{ $match->awayTeam?->name }}</strong>
        </div>
    </div>

    <div class="lv-actions">
        <button type="button" data-live-action="goal">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 4.2 14.2 8h4.2l-3.4 2.6 1.3 4.1L12 12.6 7.7 14.7l1.3-4.1L5.6 8h4.2z" fill="currentColor"/></svg>
            Gol
        </button>
        <button type="button" data-live-action="assist">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16c2.2-4 5-6 8.5-6H16V7l5 4.5L16 16v-3h-2.2C10.6 13 8 15 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Asistencia
        </button>
        <button type="button" data-live-action="yellow">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="3" width="10" height="16" rx="1.5" fill="#f5c542"/></svg>
            Amarilla
        </button>
        <button type="button" data-live-action="red">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="3" width="10" height="16" rx="1.5" fill="#e23b3b"/></svg>
            Roja
        </button>
        <button type="button" data-live-action="substitution">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h11l-3-3M17 17H6l3 3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Cambio
        </button>
        <button type="button" data-live-action="incident">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="6" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7V4M9 4h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            Incidencia
        </button>
    </div>

    <div class="lv-picker" data-live-picker hidden>
        <header>
            <strong data-live-picker-title>Elegí el jugador</strong>
            <button type="button" data-live-picker-close>Cerrar</button>
        </header>
        <p data-live-picker-hint></p>
        <div class="lv-picker-tools" data-live-staff-toggle hidden>
            <button type="button" class="is-on" data-live-who="player">Jugador</button>
            <button type="button" data-live-who="staff">Cuerpo técnico</button>
        </div>
        <div class="lv-sides" data-live-sides>
            @foreach ($liveSides as $side)
                <div data-live-side data-team-id="{{ $side['team']->id }}">
                    <span>{{ $side['team']->name }}</span>
                    @forelse ($side['players'] as $player)
                        <button
                            type="button"
                            data-live-player
                            data-team-id="{{ $side['team']->id }}"
                            data-player-id="{{ $player['id'] }}"
                        >
                            <b>{{ $player['shirt'] ?: '–' }}</b>
                            {{ $player['name'] }}
                            @unless ($player['starter'])
                                <small>Suplente</small>
                            @endunless
                        </button>
                    @empty
                        <p>Sin jugadores</p>
                    @endforelse
                    @foreach ($side['staff'] as $member)
                        <button
                            type="button"
                            data-live-staff
                            hidden
                            data-team-id="{{ $side['team']->id }}"
                            data-staff-id="{{ $member['id'] }}"
                        >
                            <b>DT</b>
                            {{ $member['name'] }}
                            <small>{{ $member['role'] }}</small>
                        </button>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="lv-incidents" data-live-incidents hidden>
            <div class="lv-incident-teams">
                @foreach ($liveSides as $side)
                    <button type="button" data-live-incident-team data-team-id="{{ $side['team']->id }}">{{ $side['team']->name }}</button>
                @endforeach
            </div>
            @foreach ($incidentKinds as $value => $label)
                <button type="button" data-live-incident="{{ $value }}">{{ $label }}</button>
            @endforeach
        </div>
        <button type="button" class="lv-skip" data-live-skip hidden>Sin asistencia</button>
    </div>

    <h3 class="ws-plays-title">Eventos del partido</h3>
    <ul class="lv-events">
        @forelse ($sheet?->events ?? [] as $event)
            <li>
                <b>{{ $event->minuteLabel() }}</b>
                <span class="is-{{ $event->tone() }}">{{ $event->typeLabel() }}</span>
                <em>{{ $event->actorName() }}</em>
            </li>
        @empty
            <li class="lv-empty">Todavía no hay jugadas. Tocá Gol, asistencia o una tarjeta.</li>
        @endforelse
    </ul>

    <div class="lv-foot">
        @if ($match->isLive())
            <form method="post" action="{{ route('workspace.categories.matches.result', [$category, $match]) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="home_score" value="{{ $homeScore }}">
                <input type="hidden" name="away_score" value="{{ $awayScore }}">
                <input type="hidden" name="status" value="paused">
                <button type="submit" class="lv-pause" data-live-pause>Pausar reloj</button>
            </form>
        @else
            <form method="post" action="{{ route('workspace.categories.matches.result', [$category, $match]) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="home_score" value="{{ $homeScore }}">
                <input type="hidden" name="away_score" value="{{ $awayScore }}">
                <input type="hidden" name="status" value="live">
                <button type="submit" class="lv-pause" data-live-start>
                    {{ $match->statusOption() === 'paused' ? 'Reanudar reloj' : 'Iniciar partido' }}
                </button>
            </form>
        @endif

        <form method="post" action="{{ route('workspace.categories.matches.close', [$category, $match]) }}" data-confirm="¿Finalizar el partido y publicar el resultado?">
            @csrf
            <button type="submit" class="lv-finish">Finalizar partido</button>
        </form>
    </div>

    @if ($lastEvent)
        <form method="post" action="{{ route('workspace.categories.matches.events.destroy', [$category, $match, $lastEvent]) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="lv-undo">Corregir acción · {{ $lastEvent->typeLabel() }} {{ $lastEvent->actorName() }}</button>
        </form>
    @endif

    <form method="post" action="{{ route('workspace.categories.matches.events', [$category, $match]) }}" data-live-event-form hidden>
        @csrf
        <input type="hidden" name="type" data-live-type>
        <input type="hidden" name="actor_kind" value="player" data-live-actor>
        <input type="hidden" name="team_id" data-live-team>
        <input type="hidden" name="player_id" data-live-player-input>
        <input type="hidden" name="team_staff_id" data-live-staff-input>
        <input type="hidden" name="assist_player_id" data-live-assist>
        <input type="hidden" name="minute" value="0" data-live-minute>
    </form>

    <form method="post" action="{{ route('workspace.categories.matches.incidents', [$category, $match]) }}" data-live-incident-form hidden>
        @csrf
        <input type="hidden" name="fair_play_kind" data-live-kind>
        <input type="hidden" name="team_id" data-live-incident-team-input>
        <input type="hidden" name="moment" value="" data-live-incident-minute>
    </form>
</section>
