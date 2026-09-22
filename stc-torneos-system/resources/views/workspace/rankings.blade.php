<x-layouts.workspace
    :title="$title"
    heading="Rankings y goleadores"
    :subheading="$tournament->name.' · '.$category->name"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <p class="ws-ficha-back">
        <a href="{{ route('workspace.categories.home', $category) }}">← {{ $category->name }}</a>
    </p>

    @include('workspace.partials.competition-tabs', ['category' => $category, 'tab' => 'rankings'])

    @if (session('status'))
        <div class="ws-alert">{{ session('status') }}</div>
    @endif

    <section class="ws-scorer" data-scorer>
        <div class="ws-scorer-tabs" role="tablist">
            @foreach ($rankings as $label => $rows)
                <button type="button" role="tab" data-scorer-tab="{{ \Illuminate\Support\Str::slug($label) }}" @class(['is-on' => $loop->first])>{{ $label }}</button>
            @endforeach
            <button type="button" role="tab" data-scorer-tab="vallas">Vallas</button>
        </div>

        @foreach ($rankings as $label => $rows)
            @php
                $metric = match ($label) {
                    'Goles' => 'goles',
                    'Asistencias' => 'asistencias',
                    'Tarjetas amarillas' => 'amarillas',
                    'Tarjetas rojas' => 'rojas',
                    default => 'puntos',
                };
                $column = match ($label) {
                    'Goles' => 'GOLES',
                    'Asistencias' => 'ASIST.',
                    'Tarjetas amarillas' => 'TA',
                    'Tarjetas rojas' => 'TR',
                    default => 'PTS',
                };
                $top = $rows->take(3)->values();
                $podium = collect([
                    2 => $top->get(1),
                    1 => $top->get(0),
                    3 => $top->get(2),
                ])->filter();
                $rest = $rows->slice(3)->values();
            @endphp
            <div data-scorer-panel="{{ \Illuminate\Support\Str::slug($label) }}" @if (! $loop->first) hidden @endif>
                @if ($rows->isEmpty())
                    <p class="ws-scorer-empty">Todavía no hay datos.</p>
                @else
                    <div class="ws-podium">
                        @foreach ($podium as $place => $row)
                            @php
                                $playerModel = $row[4] ?? null;
                                $teamModel = $row[5] ?? null;
                                $playerName = $row[0] === 'Sin jugador' ? 'Jugador no identificado' : $row[0];
                                $profileUrl = $playerModel
                                    ? route('workspace.categories.players.show', [$category, $playerModel])
                                    : null;
                            @endphp
                            <article @class(['ws-podium-slot', 'is-'.$place])>
                                <div class="ws-podium-photo">
                                    <span class="ws-podium-rank">{{ $place }}</span>
                                    @if ($profileUrl)
                                        <a class="ws-podium-face" href="{{ $profileUrl }}">
                                            <img src="{{ $row[3] ?: asset('images/defaults/player.svg') }}" alt="{{ $playerName }}">
                                        </a>
                                    @else
                                        <img src="{{ $row[3] ?: asset('images/defaults/player.svg') }}" alt="{{ $playerName }}">
                                    @endif
                                    @if ($teamModel)
                                        <img class="ws-podium-badge" src="{{ $teamModel->shieldUrl() }}" alt="{{ $teamModel->name }}">
                                    @endif
                                </div>
                                <h3>
                                    @if ($profileUrl)
                                        <a href="{{ $profileUrl }}">{{ $playerName }}</a>
                                    @else
                                        {{ $playerName }}
                                    @endif
                                </h3>
                                <p>{{ $row[1] }}</p>
                                <strong>{{ $row[2] }} {{ $metric }}</strong>
                                @if ($place === 1 && $profileUrl)
                                    <a class="ws-podium-profile" href="{{ $profileUrl }}">Ver perfil</a>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    @if ($rest->isNotEmpty())
                        <div class="ws-scorer-table">
                            <div class="ws-scorer-head">
                                <span></span>
                                <span></span>
                                <span></span>
                                <span>PJ</span>
                                <span>{{ $column }}</span>
                            </div>
                            @foreach ($rest as $offset => $row)
                                @php
                                    $playerModel = $row[4] ?? null;
                                    $playerName = $row[0] === 'Sin jugador' ? 'Jugador no identificado' : $row[0];
                                    $profileUrl = $playerModel
                                        ? route('workspace.categories.players.show', [$category, $playerModel])
                                        : null;
                                @endphp
                                <a class="ws-scorer-row" @if ($profileUrl) href="{{ $profileUrl }}" @endif>
                                    <span class="ws-scorer-pos">{{ $offset + 4 }}</span>
                                    <img src="{{ $row[3] ?: asset('images/defaults/player.svg') }}" alt="">
                                    <span class="ws-scorer-copy">
                                        <strong>{{ $playerName }}</strong>
                                        <small>{{ $row[1] }}</small>
                                    </span>
                                    <span>{{ $row[6] ?? '—' }}</span>
                                    <b>{{ $row[2] }}</b>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @endforeach

        <div data-scorer-panel="vallas" hidden>
            @if ($leastGoals->isEmpty())
                <p class="ws-scorer-empty">Todavía no hay datos.</p>
            @else
                <div class="ws-scorer-table">
                    <div class="ws-scorer-head is-teams">
                        <span></span>
                        <span></span>
                        <span>PJ</span>
                        <span>GC</span>
                    </div>
                    @foreach ($leastGoals as $row)
                        <div class="ws-scorer-row is-team">
                            <span class="ws-scorer-pos">{{ $row['position'] }}</span>
                            <img src="{{ $row['team']?->shieldUrl() ?? asset('images/stc-logo.png') }}" alt="">
                            <span class="ws-scorer-copy">
                                <strong>{{ $row['team']?->name }}</strong>
                                <small>{{ $row['gf'] }} goles a favor</small>
                            </span>
                            <span>{{ $row['played'] }}</span>
                            <b>{{ $row['ga'] }}</b>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="ws-poll-section">
        <header class="ws-poll-section-head">
            <div>
                <p class="stc-eyebrow">Encuestas</p>
                <h2>Votación de la categoría</h2>
            </div>
            @if ($canEdit ?? false)
                <button type="button" class="ws-fab" data-ws-open="add-poll" aria-label="Nueva encuesta">+</button>
            @endif
        </header>

        <div class="ws-poll-grid">
            @forelse ($polls as $poll)
                @include('workspace.partials.poll-card', [
                    'poll' => $poll,
                    'category' => $category,
                    'canEdit' => $canEdit ?? false,
                ])
            @empty
                <div class="ws-empty ws-poll-empty">Todavía no hay encuestas en esta categoría.</div>
            @endforelse
        </div>
    </section>

    @if ($canEdit ?? false)
        <x-ws-modal id="add-poll" title="Nueva encuesta">
            <x-ws-form :action="route('workspace.categories.polls.store', $category)" submit="Publicar encuesta">
                <label>Pregunta
                    <input type="text" name="question" required maxlength="500" placeholder="Ej: ¿Quién fue el mejor jugador de la fecha?">
                </label>
                <label>Opciones (una por línea)
                    <textarea name="options" rows="5" required placeholder="Opción A&#10;Opción B&#10;Opción C"></textarea>
                </label>
                <div class="ws-poll-flags">
                    <label><input type="checkbox" name="is_visible" value="1" checked> Visible</label>
                    <label><input type="checkbox" name="show_results" value="1" checked> Mostrar resultado</label>
                    <label><input type="checkbox" name="voting_open" value="1" checked> Votación abierta</label>
                    <label><input type="checkbox" name="allow_multiple" value="1"> Elija más de 1</label>
                </div>
            </x-ws-form>
        </x-ws-modal>
    @endif
</x-layouts.workspace>
