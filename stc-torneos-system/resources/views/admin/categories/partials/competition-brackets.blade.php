@if ($brackets->isEmpty())
    <div class="category-empty-state"><strong>Todavía no hay semifinales ni finales cargadas en esta categoría.</strong></div>
@else
    <section class="results-brackets">
        @foreach ($brackets as $stage => $matches)
            <article class="results-bracket-column">
                <h3>{{ $stage }}</h3>
                @foreach ($matches as $match)
                    <div class="results-bracket-card">
                        <span>
                            <strong>{{ $match->homeTeam?->name ?? 'Local' }}</strong>
                            <b>{{ $match->home_score ?? '-' }}</b>
                        </span>
                        <span>
                            <strong>{{ $match->awayTeam?->name ?? 'Visitante' }}</strong>
                            <b>{{ $match->away_score ?? '-' }}</b>
                        </span>
                        <small>{{ $match->scoreLine() }} · {{ $match->statusLabel() }} · {{ $match->field?->name }} · {{ $match->scheduled_at?->format('d/m H:i') }}</small>
                        @if ($winner = $match->winnerTeam())
                            <small>Ganador: {{ $winner->name }}</small>
                        @endif
                        <a href="{{ route('admin.fixture.show', $match) }}">Ver partido</a>
                    </div>
                @endforeach
            </article>
        @endforeach
    </section>
@endif
