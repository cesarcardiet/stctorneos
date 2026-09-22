<section class="ws-rank-boards">
    @foreach ($rankings as $title => $players)
        @php
            $metric = match ($title) {
                'Goles' => 'GOLES',
                'Asistencias' => 'ASIST.',
                'Tarjetas amarillas' => 'TA',
                'Tarjetas rojas' => 'TR',
                default => 'PTS',
            };
        @endphp
        <article class="ws-rank-board">
            <header>
                <h3 class="ws-rank-title">{{ $title }}</h3>
                <span>Jugadores</span>
                <b>{{ $metric }}</b>
            </header>
            <ol class="ws-rank-list">
                @forelse ($players as $index => $row)
                    @php
                        $playerName = $row[0] === 'Sin jugador' ? 'Jugador no identificado' : $row[0];
                        $playerModel = $row[4] ?? null;
                        $teamModel = $row[5] ?? null;
                    @endphp
                    <li class="ws-rank-player">
                        <span class="ws-rank-position">{{ $index + 1 }}</span>
                        <img src="{{ $row[3] ?? asset('images/defaults/player.svg') }}" alt="{{ $playerName }}">
                        <div class="ws-rank-copy">
                            @if ($playerModel)
                                <a class="row-title-link" href="{{ route('admin.players.show', $playerModel) }}"><strong>{{ $playerName }}</strong></a>
                            @else
                                <strong>{{ $playerName }}</strong>
                            @endif
                            @if ($teamModel)
                                <span><a href="{{ route('admin.teams.show', $teamModel) }}">{{ $row[1] }}</a></span>
                            @else
                                <span>{{ $row[1] }}</span>
                            @endif
                        </div>
                        <b>{{ $row[2] }}</b>
                    </li>
                @empty
                    <li class="ws-muted">Todavía no hay datos.</li>
                @endforelse
            </ol>
        </article>
    @endforeach
    <article class="ws-rank-board">
        <header>
            <h3 class="ws-rank-title">Vallas menos vencidas</h3>
            <span>Equipos</span>
            <b>GC</b>
        </header>
        <ol class="ws-rank-list">
            @forelse ($leastGoals as $index => $row)
                <li class="ws-rank-team">
                    <span class="ws-rank-position">{{ $index + 1 }}</span>
                    <x-entity-cell
                        :href="route('admin.teams.show', $row['team'])"
                        :src="$row['team']->shieldUrl()"
                        :alt="$row['team']->name"
                    >
                        {{ $row['team']->name }}
                    </x-entity-cell>
                    <span class="ws-rank-meta">{{ $row['played'] }} jugados</span>
                    <b>{{ $row['ga'] }}</b>
                </li>
            @empty
                <li class="ws-muted">Todavía no hay datos.</li>
            @endforelse
        </ol>
    </article>
</section>
