<x-layouts.workspace
    :title="$title"
    heading="Documentación"
    :subheading="$category->name"
    :category="$category"
    :tournament="$tournament"
    active="Configuración"
>
    <p class="ws-back"><a href="{{ route('workspace.categories.settings', $category) }}">← Configuración</a></p>

    <section class="ws-docs-page">
        <form class="ws-docs-toolbar" method="get" action="{{ route('workspace.categories.documents', $category) }}">
            <label class="ws-docs-search">
                <span>Buscar</span>
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] }}"
                    placeholder="Jugador, documento o equipo…"
                    autocomplete="off"
                >
            </label>
            <label class="ws-docs-filter">
                <span>Estado</span>
                <select name="status">
                    <option value="all" @selected($filters['status'] === 'all')>Todos</option>
                    <option value="review" @selected($filters['status'] === 'review')>Pendientes / observados</option>
                    @foreach (\App\Models\PlayerDocument::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="ws-btn">Filtrar</button>
            <a class="ws-btn ghost" href="{{ route('workspace.categories.documents', $category) }}">Limpiar</a>
        </form>

        <p class="ws-muted ws-docs-hint">Un jugador por fila. Abrí la ficha, mirá cada documento en el modal y recién ahí aprobá.</p>

        <div class="ws-docs-players">
            @forelse ($players as $player)
                @php
                    $docs = collect(\App\Models\Player::documentTypes())
                        ->map(fn (string $type) => $player->documentByType($type))
                        ->filter();
                    $pendingCount = $docs->whereIn('status', ['pending', 'observed'])->count();
                @endphp
                <details class="ws-docs-player" @if ($pendingCount > 0) open @endif>
                    <summary class="ws-docs-player-summary">
                        <img src="{{ $player->listPhotoUrl() }}" alt="">
                        <div class="ws-docs-player-copy">
                            <strong>{{ $player->fullName() }}</strong>
                            <span>{{ $player->team?->name ?? 'Sin equipo' }} · {{ $player->documentationSummary() }}</span>
                        </div>
                        <span class="ws-docs-player-meta">
                            @if ($pendingCount > 0)
                                <span class="ws-status-pill is-warn">{{ $pendingCount }} por revisar</span>
                            @else
                                <span class="ws-status-pill is-ok">Al día</span>
                            @endif
                            <span class="ws-docs-chevron" aria-hidden="true">▾</span>
                        </span>
                    </summary>

                    <ul class="ws-doc-list ws-docs-player-docs">
                        @foreach (\App\Models\Player::documentTypes() as $type)
                            @include('workspace.partials.player-doc-row', [
                                'player' => $player,
                                'category' => $category,
                                'type' => $type,
                                'label' => $type,
                                'document' => $player->documentByType($type),
                                'allowUpload' => false,
                                'allowReview' => $canEdit,
                                'canEdit' => $canEdit,
                            ])
                        @endforeach
                    </ul>

                    <footer class="ws-docs-player-foot">
                        <a class="ws-btn ghost" href="{{ route('workspace.categories.players.show', [$category, $player]) }}">Ver ficha</a>
                        @if ($canEdit)
                            <a class="ws-btn ghost" href="{{ route('workspace.categories.players.edit', [$category, $player]) }}">Editar jugador</a>
                        @endif
                    </footer>
                </details>
            @empty
                <div class="ws-empty">No hay jugadores con documentación para estos filtros.</div>
            @endforelse
        </div>

        @if ($players->hasPages())
            <footer class="ws-docs-pagination">
                {{ $players->links() }}
            </footer>
        @endif
    </section>
</x-layouts.workspace>
