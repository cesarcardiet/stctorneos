@php
    $portfolio = collect($portfolio ?? []);
    $allTeams = collect($allTeams ?? []);
    $totalTeams = $allTeams->count();
    $openTeams = $allTeams->filter(fn ($team) => $team->category && $team->category->acceptsRosterEdits($team))->count();
@endphp

<x-layouts.workspace
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :tournament="$tournament"
    active="Delegaciones"
>
    <p class="ws-back">
        <a href="{{ route('workspace.home') }}">← Torneos</a>
        @if ($tournament)
            <a href="{{ route('workspace.tournaments.show', $tournament) }}">Categorías de {{ $tournament->name }}</a>
        @endif
    </p>

    @if (session('status'))
        <div class="ws-alert">{{ session('status') }}</div>
    @endif

    <section class="ws-ficha">
        <article class="ws-card">
            <p class="stc-eyebrow">Resumen</p>
            <ul class="ws-meta">
                <li>Torneos <strong>{{ $portfolio->count() }}</strong></li>
                <li>Equipos <strong>{{ $totalTeams }}</strong></li>
                <li>Inscripciones abiertas <strong>{{ $openTeams }}</strong></li>
            </ul>
            @if ($portfolio->count() > 1)
                <p class="ws-muted">Tenés delegaciones en varios torneos. Acá ves todos los equipos juntos en un solo listado.</p>
            @endif
        </article>

        <article class="ws-card">
            <header class="ws-card-head">
                <h3>Todos los equipos</h3>
                <span class="ws-muted">{{ $totalTeams }} en total{{ $openTeams > 0 ? ' · '.$openTeams.' con inscripción abierta' : '' }}</span>
            </header>
            <div class="ws-list">
                @php $lastTournamentId = null; @endphp
                @forelse ($allTeams as $team)
                    @php
                        $teamTournamentId = (int) ($team->category?->tournament_id ?? $team->tournament_id);
                        $showTournamentHeading = $portfolio->count() > 1 && $teamTournamentId !== $lastTournamentId;
                        $lastTournamentId = $teamTournamentId;
                    @endphp
                    @if ($showTournamentHeading)
                        <div class="ws-list-section">
                            <strong>{{ $team->category?->tournament?->name ?? 'Torneo' }}</strong>
                            <span>{{ $team->delegation?->name }}</span>
                        </div>
                    @endif
                    @include('workspace.partials.my-club-team-row', [
                        'team' => $team,
                        'highlight' => $team->category && $team->category->acceptsRosterEdits($team),
                    ])
                @empty
                    <div class="ws-empty">Todavía no hay equipos vinculados a tu club.</div>
                @endforelse
            </div>
        </article>

        @foreach ($portfolio as $group)
            @php $club = $group['club']; @endphp
            <article class="ws-card @if(($highlightTournamentId ?? null) && (int) $club->tournament_id === (int) $highlightTournamentId) is-highlight @endif">
                <p class="stc-eyebrow">{{ $group['tournament']?->name ?? 'Torneo' }}</p>
                <div class="ws-team-identity">
                    <img class="ws-team-shield" src="{{ $club->logoUrl() }}" alt="">
                    <div>
                        <h3>{{ $club->name }}</h3>
                        <p class="ws-muted">{{ $club->originLabel() }} · {{ $group['teams']->count() }} equipos</p>
                    </div>
                </div>
            </article>
        @endforeach
    </section>
</x-layouts.workspace>
