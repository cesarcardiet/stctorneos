<x-layouts.stc
    :title="'Rankings | '.$category->name"
    active="Clasificación"
    heading="Rankings y goleadores"
    :subheading="$category->tournament->name.' · '.$category->name"
>
    <a class="back-link" href="{{ route('admin.categories.show', $category) }}">← {{ $category->name }}</a>

    @include('admin.categories.partials.competition-tabs', ['category' => $category, 'tab' => 'rankings'])

    @if (($categories ?? collect())->count() > 1)
        <label class="competition-category-switch">
            <span>Categoría</span>
            <select onchange="location.href=this.value" aria-label="Cambiar categoría">
                @foreach ($categories as $item)
                    <option value="{{ route('admin.categories.rankings', $item) }}" @selected($item->id === $category->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </label>
    @endif

    <section class="competition-layout">
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

        <aside class="matches-panel">
            <header>
                <h3>Partidos</h3>
                <form class="match-panel-filter" method="get" action="{{ route('admin.categories.rankings', $category) }}">
                    @if (request()->filled('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    <label>Fase
                        <select name="phase" onchange="this.form.submit()">
                            <option value="all" @selected($selectedPhase === 'all')>Todas las fases</option>
                            @foreach ($phases as $phase)
                                <option value="{{ $phase }}" @selected($selectedPhase === $phase)>{{ $phase }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Fecha
                        <select name="date" onchange="this.form.submit()">
                            <option value="all" @selected($selectedDate === 'all')>Todas las fechas</option>
                            @foreach ($dates as $date)
                                <option value="{{ $date }}" @selected($selectedDate === $date)>{{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
            </header>

            @foreach ($visibleMatches->take(4) as $match)
                <article class="match-mini-card">
                    <div>
                        <img src="{{ $match->homeTeam?->shieldUrl() ?? asset('images/stc-logo.png') }}" alt="{{ $match->homeTeam?->name }}">
                        @if ($match->homeTeam)
                            <a class="row-title-link" href="{{ route('admin.teams.show', $match->homeTeam) }}"><strong>{{ $match->homeTeam->name }}</strong></a>
                        @else
                            <strong>Local</strong>
                        @endif
                    </div>
                    <span class="score">{{ $match->home_score ?? 0 }} : {{ $match->away_score ?? 0 }}</span>
                    <div>
                        <img src="{{ $match->awayTeam?->shieldUrl() ?? asset('images/stc-logo.png') }}" alt="{{ $match->awayTeam?->name }}">
                        @if ($match->awayTeam)
                            <a class="row-title-link" href="{{ route('admin.teams.show', $match->awayTeam) }}"><strong>{{ $match->awayTeam->name }}</strong></a>
                        @else
                            <strong>Visitante</strong>
                        @endif
                    </div>
                    <small>
                        {{ strtoupper($match->stage) }}
                        @if ($match->field)
                            · <a href="{{ route('admin.fields.show', $match->field) }}">{{ $match->field->name }}</a>
                        @endif
                        · {{ $match->scheduled_at?->format('d/m/Y H:i') }}
                    </small>
                </article>
            @endforeach

            <a class="stc-button full" href="{{ route('admin.fixture.create') }}">Agregar partido</a>

            <section class="date-stats-card">
                <h3>Estadísticas de la fecha</h3>
                <div class="date-stats-circles">
                    <span>Juegos <strong>{{ $dateStats['games'] }}</strong></span>
                    <span>Goles <strong>{{ $dateStats['goals'] }}</strong></span>
                </div>
            </section>
        </aside>
    </section>
</x-layouts.stc>
