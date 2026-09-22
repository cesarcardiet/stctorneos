<x-layouts.stc
    title="Rankings | STC Torneos"
    active="Resultados"
    heading="Goleadores y vallas"
    :subheading="($category?->tournament?->name ?? 'Torneo').' · '.($category?->name ?? 'Elegí una categoría').' · eventos de planillas cerradas'"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @include('admin.results.partials.tabs')

    <section class="results-rankings-grid">
        <div class="ranking-stack">
            @foreach ($rankings as $title => $players)
                <article class="ranking-card">
                    <header>
                        <strong>{{ $title }}</strong>
                        <span>Jugadores</span>
                        <span>{{ $title === 'Goles' ? 'Goles' : ($title === 'Asistencias' ? 'Asist.' : ($title === 'Tarjetas amarillas' ? 'TA' : 'TR')) }}</span>
                    </header>
                    @forelse ($players as [$player, $team, $value, $photo, $playerModel, $teamModel])
                        <div class="ranking-row">
                            <b>{{ $loop->iteration }}</b>
                            <img class="avatar" src="{{ $photo ?: asset('images/defaults/player.svg') }}" alt="{{ $player }}">
                            <span>
                                @if ($playerModel)
                                    <a class="row-title-link" href="{{ route('admin.players.show', $playerModel) }}"><strong>{{ $player }}</strong></a>
                                @else
                                    <strong>{{ $player }}</strong>
                                @endif
                                @if ($teamModel)
                                    <small><a href="{{ route('admin.teams.show', $teamModel) }}">{{ $team }}</a></small>
                                @else
                                    <small>{{ $team }}</small>
                                @endif
                            </span>
                            <em>{{ $value }}</em>
                        </div>
                    @empty
                        <div class="ranking-row results-empty-rank"><span><strong>Sin eventos de este tipo.</strong></span></div>
                    @endforelse
                </article>
            @endforeach
        </div>

        <aside class="results-side-stack">
            <article class="standings-card">
                <header>
                    <span>Vallas menos vencidas</span>
                    <small>Menos goles recibidos</small>
                </header>
                <div class="standings-table results-vallas-table">
                    <div class="table-head">
                        <span>Pos</span><span>Equipo</span><span>J</span><span>GC</span><span>GF</span>
                    </div>
                    @forelse ($vallas as $row)
                        <div class="table-row">
                            <span>{{ $row['position'] }}</span>
                            <span>
                                <x-entity-cell
                                    :href="route('admin.teams.show', $row['team'])"
                                    :src="$row['team']->shieldUrl()"
                                    :alt="$row['team']->name"
                                >
                                    {{ strtoupper($row['team']->name) }}
                                </x-entity-cell>
                            </span>
                            <span>{{ $row['played'] }}</span>
                            <span>{{ $row['ga'] }}</span>
                            <span>{{ $row['gf'] }}</span>
                        </div>
                    @empty
                        <div class="category-empty-state"><strong>Todavía no hay partidos cerrados.</strong></div>
                    @endforelse
                </div>
            </article>

            <article class="standings-card">
                <header>
                    <span>Estadísticas por equipo</span>
                    <small>Todos los partidos cerrados</small>
                </header>
                <div class="standings-table results-teamstats-table">
                    <div class="table-head">
                        <span>Equipo</span><span>J</span><span>GF</span><span>GC</span><span>DIF</span>
                    </div>
                    @forelse ($teamStats as $row)
                        <div class="table-row">
                            <span>
                                <x-entity-cell
                                    :href="route('admin.teams.show', $row['team'])"
                                    :src="$row['team']->shieldUrl()"
                                    :alt="$row['team']->name"
                                >
                                    {{ strtoupper($row['team']->name) }}
                                </x-entity-cell>
                            </span>
                            <span>{{ $row['played'] }}</span>
                            <span>{{ $row['gf'] }}</span>
                            <span>{{ $row['ga'] }}</span>
                            <span>{{ $row['gd'] }}</span>
                        </div>
                    @empty
                        <div class="category-empty-state"><strong>Sin estadísticas de equipo.</strong></div>
                    @endforelse
                </div>
            </article>
        </aside>
    </section>
</x-layouts.stc>
