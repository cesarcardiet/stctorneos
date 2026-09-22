<x-layouts.workspace title="Operación · Torneos" heading="Elegí un torneo" subheading="Entrá a un torneo y después a la categoría. El menú cambia cuando estás adentro." active="Torneos">
    <section class="ws-gate-tourneys" data-ws-tourney-board>
        <header class="ws-gate-tourneys-head">
            <p class="ws-gate-tourneys-count" data-ws-tourney-count>
                {{ $tournaments->count() }} {{ $tournaments->count() === 1 ? 'torneo' : 'torneos' }}
            </p>

            <div class="ws-gate-tourneys-tools">
                @if ($tournaments->isNotEmpty())
                    <div class="ws-tourney-search ws-gate-tourneys-search">
                        <input
                            type="search"
                            data-ws-tourney-search
                            placeholder="Buscar torneo, ciudad o edición…"
                            autocomplete="off"
                            aria-label="Buscar torneo"
                        >
                    </div>
                @endif

                @if (! empty($canCreateTournaments))
                    <a class="stc-pill-btn" href="{{ route('admin.tournaments.create') }}">+ Crear torneo</a>
                @endif
            </div>
        </header>

        <div class="ws-gate-tourneys-grid">
            @forelse ($tournaments as $item)
                @php
                    $canEditItem = ! empty($canEditTournaments) && auth()->user()?->canAccessTournament((int) $item->id);
                    $searchText = trim(implode(' ', array_filter([
                        $item->name,
                        $item->edition,
                        $item->city,
                        $item->country,
                        $item->venue_name,
                        $item->statusLabel(),
                    ])));
                @endphp
                <article
                    class="ws-tourney-card"
                    data-ws-tourney-item
                    data-search="{{ $searchText }}"
                >
                    <div class="ws-tourney-card-emblem" aria-hidden="true">
                        <img src="{{ $item->logoUrl() }}" alt="">
                    </div>

                    <div class="ws-tourney-card-body">
                        <div class="ws-tourney-badges">
                            <span @class(['ws-tourney-status', 'is-'.$item->status])>{{ $item->statusLabel() }}</span>
                            @if ($item->edition)
                                <span class="ws-tourney-edition">{{ $item->edition }}</span>
                            @endif
                        </div>

                        <h3>
                            <a href="{{ route('workspace.tournaments.show', $item) }}">{{ $item->name }}</a>
                        </h3>

                        <p class="ws-tourney-meta">
                            {{ collect([$item->city, $item->country])->filter()->implode(' · ') ?: 'Sin sede' }}
                            · {{ $item->starts_at?->format('d/m/Y') ?: '—' }} – {{ $item->ends_at?->format('d/m/Y') ?: '—' }}
                        </p>

                        <div class="ws-tourney-chips">
                            <span><b>{{ $item->categories_count }}</b> cat.</span>
                            <span><b>{{ $item->teams_count }}</b> equipos</span>
                        </div>
                    </div>

                    <div class="ws-tourney-card-actions">
                        <a class="stc-pill-btn" href="{{ route('workspace.tournaments.show', $item) }}">Entrar</a>
                        @if ($canEditItem)
                            <a class="stc-pill-btn ghost" href="{{ route('admin.tournaments.edit', $item) }}">Editar</a>
                        @endif
                        @if (! empty($canDeleteTournaments))
                            <form method="post" action="{{ route('workspace.tournaments.destroy', $item) }}" onsubmit="return confirm('¿Eliminar {{ $item->name }}? Se borran categorías, equipos y partidos de ese torneo.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="stc-pill-btn danger">Eliminar</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="ws-empty ws-gate-tourneys-empty">
                    <strong>No hay torneos en tu alcance.</strong>
                    @if (! empty($canCreateTournaments))
                        <p>Creá el primero para empezar a cargar categorías, equipos y fixture.</p>
                        <a class="stc-pill-btn" href="{{ route('admin.tournaments.create') }}">+ Crear torneo</a>
                    @endif
                </div>
            @endforelse
        </div>

        <p class="ws-gate-tourneys-no-match" data-ws-tourney-empty hidden>
            No hay torneos que coincidan con esa búsqueda.
        </p>
    </section>
</x-layouts.workspace>
