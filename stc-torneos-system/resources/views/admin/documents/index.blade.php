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
            @foreach ($accessibleTournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
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
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar jugador, documento o equipo">
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.documents.index') }}">Limpiar</a>
        <a class="admin-figma-export" href="{{ route('admin.documents.export', request()->query()) }}">Exportar</a>
    </form>

    <section class="stc-card table-card team-list-panel player-list-panel docs-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Bandeja documental</h3>
            <a href="{{ route('admin.documents.requirements') }}">Requisitos</a>
        </header>
        <div class="stc-table docs-table admin-figma-table">
            <div class="table-head">
                <span>Jugador</span>
                <span>Delegación</span>
                <span>Equipo</span>
                <span>Documento</span>
                <span>Subido</span>
                <span>Estado</span>
                <span>Habilitación</span>
                <span>Acciones</span>
            </div>
            @forelse ($documents as $document)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.documents.show', $document)"
                            :src="$document->player?->listPhotoUrl() ?? asset('images/defaults/player.svg')"
                            :alt="$document->player?->fullName() ?? 'Sin jugador'"
                            shape="round"
                        >
                            {{ $document->player?->fullName() ?? 'Sin jugador' }}
                            <x-slot:subtitle>{{ $document->player?->formattedDocument() }}</x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>
                        @if ($document->player?->team?->delegation)
                            <x-entity-ref :href="route('admin.delegations.show', $document->player->team->delegation)" :src="$document->player->team->delegation->logoUrl()" :alt="$document->player->team->delegation->name">
                                {{ $document->player->team->delegation->name }}
                            </x-entity-ref>
                        @else
                            {{ $document->player?->team?->delegation_name ?? '—' }}
                        @endif
                    </span>
                    <span>
                        @if ($document->player?->team)
                            <x-entity-ref :href="route('admin.teams.show', $document->player->team)" :src="$document->player->team->shieldUrl()" :alt="$document->player->team->name">
                                {{ $document->player->team->name }}
                            </x-entity-ref>
                            @if ($document->player->team->category)
                                <small><a href="{{ route('admin.categories.show', $document->player->team->category) }}">{{ $document->player->team->category->name }}</a></small>
                            @endif
                        @else
                            —
                        @endif
                    </span>
                    <span>{{ $document->type }}</span>
                    <span>{{ $document->uploaded_at?->format('d/m/Y') ?: '—' }}</span>
                    <span @class(['admin-status', 'is-approved' => $document->status === 'approved', 'is-observed' => in_array($document->status, ['pending', 'observed'], true), 'is-blocked' => $document->status === 'rejected'])>{{ $document->statusLabel() }}</span>
                    <span>{{ $document->player?->eligibilityLabel() ?? '—' }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.documents.show', $document) }}">{{ $document->actionLabel() }}</a>
                        @if ($document->player)
                            <a href="{{ route('admin.players.show', $document->player) }}">Ficha</a>
                        @endif
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span>
                        <strong>No hay documentos con esos filtros.</strong>
                        <small>Cambiá filtros o revisá la lista de buena fe.</small>
                    </span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.documents.index') }}">Ver todos</a>
                    </span>
                </div>
            @endforelse
        </div>
        @if ($documents->hasPages())
            <footer class="admin-list-pagination">
                {{ $documents->links() }}
            </footer>
        @endif
    </section>
</x-layouts.stc>
