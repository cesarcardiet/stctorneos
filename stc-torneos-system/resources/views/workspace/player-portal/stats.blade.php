<x-layouts.workspace
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <section class="ws-ficha">
        <section class="ws-kpis">
            <article class="ws-kpi tone-ok">
                <strong>{{ $stats['goals'] }}</strong>
                <span>Goles</span>
            </article>
            <article class="ws-kpi tone-ok">
                <strong>{{ $stats['assists'] }}</strong>
                <span>Asistencias</span>
            </article>
            <article class="ws-kpi tone-warn">
                <strong>{{ $stats['yellows'] }}</strong>
                <span>Amarillas</span>
            </article>
            <article class="ws-kpi tone-warn">
                <strong>{{ $stats['reds'] }}</strong>
                <span>Rojas</span>
            </article>
        </section>

        <article class="ws-card">
            <p class="stc-eyebrow">Resumen de temporada</p>
            <ul class="player-data-list">
                <li><span>Partidos con jugadas registradas</span><strong>{{ $stats['matches_played'] }}</strong></li>
                <li><span>Partidos del equipo (finalizados)</span><strong>{{ $stats['team_matches'] }}</strong></li>
                <li><span>Próximos / en juego</span><strong>{{ $stats['upcoming'] }}</strong></li>
                <li><span>Equipo</span><strong>{{ $player->team?->name ?? '—' }}</strong></li>
                <li><span>Categoría</span><strong>{{ $player->team?->category?->name ?? '—' }}</strong></li>
            </ul>
            @if ($player->team?->category)
                <p class="ws-muted" style="margin-top: .75rem;">
                    También podés ver la tabla general, el calendario y los rankings de tu categoría.
                </p>
                <div class="ws-row-actions" style="margin-top: .75rem;">
                    <a class="ws-btn ghost" href="{{ route('workspace.categories.standings', $player->team->category) }}">Clasificación</a>
                    <a class="ws-btn ghost" href="{{ route('workspace.categories.fixture', $player->team->category) }}">Fixture</a>
                    <a class="ws-btn ghost" href="{{ route('workspace.categories.rankings', $player->team->category) }}">Rankings</a>
                </div>
            @endif
        </article>

        @if ($recentEvents->isNotEmpty())
            <article class="ws-card">
                <p class="stc-eyebrow">Últimas jugadas</p>
                <ul class="ws-plays-list">
                    @foreach ($recentEvents as $event)
                        @php($match = $event->sheet?->match)
                        <li @class(['ws-play-card', 'is-'.$event->tone()])>
                            <x-ws-event-icon :type="$event->iconType()" />
                            <div class="ws-play-copy">
                                <strong>{{ $event->headline() }}</strong>
                                <span>
                                    @if ($match)
                                        {{ $match->homeTeam?->name }} vs {{ $match->awayTeam?->name }}
                                    @endif
                                    · {{ $event->minuteLabel() }}
                                </span>
                                @if ($event->detail)
                                    <small>{{ $event->detail }}</small>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </article>
        @endif

        <article class="ws-card">
            <header class="ws-card-head">
                <h3>Historial de partidos</h3>
                <a href="{{ route('workspace.player.home') }}">Volver a Mi ficha</a>
            </header>

            <div class="ws-fixture-table">
                @forelse ($matchHistory as $row)
                    @php($match = $row['match'])
                    <div @class(['ws-fixture-row', 'is-live' => $match->isLive()])>
                        <div class="ws-fixture-match">
                            <div class="ws-fixture-sides">
                                <x-ws-team-mark :team="$match->homeTeam" />
                                <b class="ws-fixture-score">
                                    @if ($match->home_score === null && $match->away_score === null)
                                        vs
                                    @else
                                        {{ $match->home_score ?? 0 }} : {{ $match->away_score ?? 0 }}
                                    @endif
                                </b>
                                <x-ws-team-mark :team="$match->awayTeam" />
                            </div>
                            <div class="ws-fixture-tags">
                                <em class="ws-fixture-tag">{{ \App\Support\CategoryWorkspace::normalizeRound($match->round, $match->stage) ?: 'Sin fecha' }}</em>
                                <em class="ws-fixture-tag">{{ $row['result_label'] }}</em>
                            </div>
                        </div>
                        <span class="ws-row-actions">
                            @if ($player->team?->category)
                                <a href="{{ route('workspace.categories.matches.show', [$player->team->category, $match]) }}">Ver partido</a>
                            @endif
                        </span>
                        <div class="ws-fixture-meta">
                            <span>{{ $match->scheduled_at?->format('d/m H:i') ?: 'Sin horario' }}</span>
                            <span>{{ $match->field?->name ?: 'Sin cancha' }}</span>
                            <span>{{ $match->statusLabel() }}</span>
                        </div>
                        @if ($row['events']->isNotEmpty())
                            <div class="ws-plays-summary" style="margin-top: .5rem;">
                                @foreach ($row['events'] as $event)
                                    <span @class(['ws-plays-chip', 'is-'.$event->tone()]) title="{{ $event->typeLabel() }} · {{ $event->minuteLabel() }}">
                                        <x-ws-event-icon :type="$event->iconType()" size="xs" />
                                        <small>{{ $event->minuteLabel() }}</small>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="ws-muted" style="margin: .35rem 0 0;">Sin jugadas tuyas registradas en este partido.</p>
                        @endif
                    </div>
                @empty
                    <p class="ws-muted">Todavía no hay partidos cargados para tu equipo.</p>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.workspace>
