<x-layouts.workspace
    :title="$title"
    heading="Clasificación"
    :subheading="$tournament->name.' · '.$category->name.' · Cruces y eliminatorias'"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    @include('workspace.partials.competition-tabs', ['category' => $category, 'tab' => 'brackets'])

    @if ($brackets->isEmpty())
        <div class="ws-empty">Todavía no hay semifinales ni finales cargadas en esta categoría.</div>
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
                            <a href="{{ route('workspace.categories.matches.show', [$category, $match]) }}">Ver partido</a>
                        </div>
                    @endforeach
                </article>
            @endforeach
        </section>
    @endif
</x-layouts.workspace>
