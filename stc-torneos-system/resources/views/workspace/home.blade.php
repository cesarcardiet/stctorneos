<x-layouts.workspace
    :title="$title"
    :heading="$category->name"
    :subheading="$tournament->name"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <section class="ws-hero" style="--ws-accent: {{ $workspace['accent_color'] }}">
        <img src="{{ $category->bannerUrl() }}" alt="">
        <div>
            <p class="stc-eyebrow">{{ $tournament->name }}</p>
            <h2>{{ $category->name }}</h2>
            <p>{{ $workspace['description'] ?: 'Ficha de la categoría. Desde acá se opera el campeonato.' }}</p>
            <ul class="ws-meta">
                <li>Desde <strong>{{ $tournament->starts_at?->format('d/m/Y') ?: '—' }}</strong></li>
                <li>Final <strong>{{ $tournament->ends_at?->format('d/m/Y') ?: '—' }}</strong></li>
                <li>Formato <strong>{{ $category->competition_format }}</strong></li>
                @if ($category->groups_count > 0)
                    <li>Grupos <strong>{{ $category->groups_count }}</strong></li>
                @endif
            </ul>
        </div>
    </section>

    <section class="ws-home-standings">
        <header class="ws-card-head">
            <h3>Tabla de posiciones</h3>
            <nav class="ws-row-actions">
                <a href="{{ route('workspace.categories.standings', $category) }}">Ver clasificación</a>
                <a href="{{ route('workspace.categories.fixture', $category) }}">Fixture</a>
            </nav>
        </header>
        @include('workspace.partials.standings-tables')
    </section>

    @if ($category->rules)
        <article class="ws-card">
            <h3>Reglas del campeonato</h3>
            <p>{{ $category->rules }}</p>
        </article>
    @endif

    <article class="ws-card">
        <header class="ws-card-head">
            <h3>Equipos</h3>
            <a href="{{ route('workspace.categories.teams', $category) }}">Ver todos</a>
        </header>
        <div class="ws-shields">
            @forelse ($teams as $team)
                <a href="{{ route('workspace.categories.players', [$category, 'team_id' => $team->id]) }}">
                    <img class="ws-team-shield" src="{{ $team->shieldUrl() }}" alt="">
                    <strong>{{ strtoupper($team->name) }}</strong>
                    @if ($team->countryName())
                        <span class="ws-shield-country">{{ strtoupper($team->countryName()) }}</span>
                    @endif
                    <small>{{ $team->group_name ? $category->groupDisplayName($team->group_name) : 'Sin grupo' }} · {{ $team->players_count }} jug.</small>
                </a>
            @empty
                <p class="ws-muted">Todavía no hay equipos. Cargalos desde Configuración o Clasificación.</p>
            @endforelse
        </div>
    </article>

    @if (! empty($canEdit))
        @include('workspace.partials.config-modals')
    @endif
</x-layouts.workspace>
