<x-layouts.workspace
    :title="$title"
    heading="Equipos"
    :subheading="$category->name"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <p class="ws-back">
        <a href="{{ route('workspace.tournaments.show', $tournament) }}">← Categorías</a>
        <a href="{{ route('workspace.categories.players', $category) }}">Jugadores</a>
    </p>

    @if ($canEdit)
        <article class="ws-card">
            <p class="stc-eyebrow">Equipo de esta categoría</p>
            <p class="ws-muted">Elegí el club. El escudo y el delegado salen de esa delegación.</p>
            <form class="ws-inline-add ws-team-add" method="post" action="{{ route('workspace.categories.teams.store', $category) }}" enctype="multipart/form-data">
                @csrf
                <img class="ws-team-add-preview" data-ws-shield-preview src="{{ asset('images/stc-logo.png') }}" alt="" hidden>
                <input type="text" name="name" data-ws-team-name value="{{ old('name') }}" placeholder="Se completa al elegir el club" required>
                <select name="delegation_id" data-ws-club-select @required($clubs->isNotEmpty())>
                    <option value="">Elegí el club</option>
                    @foreach ($clubs as $club)
                        <option
                            value="{{ $club->id }}"
                            data-name="{{ $club->name }}"
                            data-logo="{{ $club->logoUrl() }}"
                            data-city="{{ $club->city }}"
                            data-country="{{ $club->country }}"
                            data-delegate="{{ $club->delegate_name }}"
                            @selected((int) old('delegation_id') === (int) $club->id)
                        >{{ $club->name }}</option>
                    @endforeach
                </select>
                <select name="group_name" @required(count($category->groupLetters()) > 0)>
                    <option value="">Grupo</option>
                    @foreach ($category->groupOptions() as $letter)
                        <option value="{{ $letter }}" @selected(old('group_name') === $letter)>{{ $category->groupDisplayName($letter) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="ws-btn">Añadir equipo</button>
            </form>
            <p class="ws-team-add-meta ws-muted" data-ws-club-meta @if (! old('delegation_id')) hidden @endif></p>
            @if ($clubs->isEmpty())
                <p class="ws-muted">Primero creá el club en <a href="{{ route('workspace.categories.clubs', $category) }}">Delegaciones</a>.</p>
            @endif
        </article>
    @endif

    <p class="ws-muted">Total: {{ $teams->count() }}</p>

    <div class="ws-list">
        @foreach ($teams as $team)
            <a class="ws-list-row" href="{{ route('workspace.categories.teams.show', [$category, $team]) }}">
                <img class="ws-team-shield" src="{{ $team->shieldUrl() }}" alt="">
                <div>
                    <strong><x-ws-flag :team="$team" /> {{ strtoupper($team->name) }}</strong>
                    <span>{{ $team->delegation?->name ?? $team->delegation_name }} · {{ $category->groupDisplayName($team->group_name) }}{{ $team->countryName() ? ' · '.$team->countryName() : '' }} · {{ $team->players_count }} jugadores</span>
                </div>
                <b>›</b>
            </a>
        @endforeach
    </div>
</x-layouts.workspace>
