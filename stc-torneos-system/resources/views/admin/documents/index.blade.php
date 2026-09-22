<x-layouts.stc
    title="Documentación | STC Torneos"
    active="Documentación"
    heading="Documentación"
    :subheading="$subheading"
    :tournament="$tournament ?? null"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <section class="admin-figma-kpis">
        @foreach ($stats as [$value, $label, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <form class="category-filter-bar player-filter-bar docs-filter-bar" method="get" action="{{ route('admin.documents.index') }}">
        <select name="type">
            <option value="all">Tipo: Todos</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected($filters['type'] === $type)>Tipo: {{ $type }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Estado: Todos</option>
            <option value="review" @selected($filters['status'] === 'review')>Estado: Pendientes y observados</option>
            @foreach (\App\Models\PlayerDocument::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>Estado: {{ $label }}</option>
            @endforeach
        </select>
        <select name="category_id">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="tournament_id">
            <option value="">Todos los torneos</option>
            @foreach ($accessibleTournaments as $item)
                <option value="{{ $item->id }}" @selected((string) $filters['tournament_id'] === (string) $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="delegation_id">
            <option value="">Todas las delegaciones</option>
            @foreach ($delegations as $delegation)
                <option value="{{ $delegation->id }}" @selected((string) $filters['delegation_id'] === (string) $delegation->id)>{{ $delegation->name }}</option>
            @endforeach
        </select>
        <select name="team_id">
            <option value="">Todos los equipos</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected((string) $filters['team_id'] === (string) $team->id)>{{ $team->name }} · {{ $team->category?->name ?? $team->category?->birth_year }}</option>
            @endforeach
        </select>
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar jugador o equipo">
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.documents.index') }}">Limpiar</a>
        <a class="admin-figma-export" href="{{ route('admin.documents.export', request()->query()) }}">Exportar</a>
    </form>

    <section class="stc-card table-card docs-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Bandeja por jugador</h3>
            <a href="{{ route('admin.documents.requirements') }}">Requisitos</a>
        </header>

        <div class="docs-player-list">
            @forelse ($players as $player)
                @php
                    $pendingCount = $player->documents
                        ->whereIn('type', \App\Models\Player::documentTypes())
                        ->whereIn('status', ['pending', 'observed'])
                        ->count();
                @endphp
                <details class="docs-player-card" @if ($pendingCount > 0) open @endif>
                    <summary class="docs-player-summary">
                        <x-entity-cell
                            :href="route('admin.players.show', [$player, 'tab' => 'aprobacion'])"
                            :src="$player->listPhotoUrl()"
                            :alt="$player->fullName()"
                            shape="round"
                        >
                            {{ $player->fullName() }}
                            <x-slot:subtitle>
                                {{ $player->team?->name ?? 'Sin equipo' }}
                                @if ($player->team?->category)
                                    · {{ $player->team->category->name }}
                                @endif
                            </x-slot:subtitle>
                        </x-entity-cell>
                        <span class="docs-player-summary-meta">
                            <span @class(['admin-status', 'is-observed' => $pendingCount > 0, 'is-approved' => $pendingCount === 0])>
                                {{ $pendingCount > 0 ? $pendingCount.' por revisar' : $player->documentationSummary() }}
                            </span>
                            <span class="docs-player-chevron" aria-hidden="true">▾</span>
                        </span>
                    </summary>

                    <div class="docs-player-docs">
                        @foreach (\App\Models\Player::documentTypes() as $type)
                            @php
                                $document = $player->documentByType($type);
                                $url = $document?->fileUrl();
                                $isImage = (bool) ($document?->isImage() && $url);
                                $isHtml = (bool) ($document?->isHtmlDocument() && $url);
                                $previewKind = $isImage ? 'image' : ($isHtml ? 'html' : 'file');
                                $canReviewDoc = $canApprove
                                    && $document
                                    && $url
                                    && ! $document->wasSignedByGuardian()
                                    && ($document->requiresClubApproval() || ! in_array($type, \App\Models\Player::authorizationDocumentTypes(), true));
                            @endphp
                            <article class="docs-player-doc-row">
                                <div class="docs-player-doc-copy">
                                    <strong>{{ $type }}</strong>
                                    @if ($document)
                                        <span @class(['admin-status', 'is-approved' => $document->status === 'approved', 'is-observed' => in_array($document->status, ['pending', 'observed'], true), 'is-blocked' => $document->status === 'rejected'])>
                                            {{ $document->wasSignedByGuardian() ? 'Firmado por tutor' : $document->statusLabel() }}
                                        </span>
                                    @else
                                        <span class="admin-status is-observed">Sin cargar</span>
                                    @endif
                                </div>
                                <div class="docs-player-doc-actions">
                                    @if ($url)
                                        <button
                                            type="button"
                                            class="stc-btn-new"
                                            data-ws-doc-preview
                                            data-preview-src="{{ $url }}"
                                            data-preview-title="{{ $type }}"
                                            data-preview-kind="{{ $previewKind }}"
                                        >Ver</button>
                                    @endif
                                    @if ($canReviewDoc && $document->status !== 'approved')
                                        <form method="post" action="{{ route('admin.players.documents.review', [$player, $document]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="stc-btn-new">Aprobar</button>
                                        </form>
                                    @elseif ($canReviewDoc)
                                        <span class="admin-status is-approved">Aprobado</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <footer class="docs-player-foot">
                        <a href="{{ route('admin.players.show', [$player, 'tab' => 'aprobacion']) }}">Abrir ficha de aprobación</a>
                        @if ($canApprove && $pendingCount > 0)
                            <form method="post" action="{{ route('admin.players.documents.approve-all', $player) }}" data-confirm="¿Aprobar toda la documentación cargada de {{ $player->fullName() }}?">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="stc-btn-new">Aprobar toda la documentación</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.players.edit', $player) }}">Editar jugador</a>
                    </footer>
                </details>
            @empty
                <div class="category-empty-state">
                    <strong>No hay jugadores con esos filtros.</strong>
                    <small>Cambiá filtros o revisá la lista de buena fe.</small>
                </div>
            @endforelse
        </div>

        @if ($players->hasPages())
            <footer class="admin-list-pagination">
                {{ $players->links() }}
            </footer>
        @endif
    </section>
</x-layouts.stc>
