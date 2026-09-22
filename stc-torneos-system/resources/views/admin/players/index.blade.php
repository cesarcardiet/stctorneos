<x-layouts.stc
    title="Lista de Buena Fe | STC Torneos"
    active="Jugadores"
    heading="Lista de Buena Fe"
    subheading="Fichas, aprobación documental y habilitación desde cada jugador · Carga primaria desde App Delegado."
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <form class="category-filter-bar player-filter-bar" method="get" action="{{ route('admin.players.index') }}">
        @if (request()->filled('tournament_id'))
            <input type="hidden" name="tournament_id" value="{{ request('tournament_id') }}">
        @endif
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar por nombre o documento">
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
        <select name="category_id">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Estado ficha: todos</option>
            <option value="review" @selected($filters['status'] === 'review')>En revisión</option>
            @foreach (\App\Models\Player::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="documentation">
            <option value="all" @selected($filters['documentation'] === 'all')>Documentación: todas</option>
            <option value="complete" @selected($filters['documentation'] === 'complete')>Completa</option>
            <option value="incomplete" @selected($filters['documentation'] === 'incomplete')>Incompleta</option>
            <option value="observed" @selected($filters['documentation'] === 'observed')>Observada</option>
        </select>
        <select name="eligibility">
            <option value="all" @selected($filters['eligibility'] === 'all')>Elegibilidad: todas</option>
            <option value="enabled" @selected($filters['eligibility'] === 'enabled')>Habilitado</option>
            <option value="not_enabled" @selected($filters['eligibility'] === 'not_enabled')>No habilitado</option>
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.players.index', ['status' => 'review']) }}">Pendientes de aprobación</a>
        <a href="{{ route('admin.documents.requirements') }}">Requisitos docs</a>
        <a href="{{ route('admin.players.index') }}">Limpiar</a>
    </form>

    <section class="admin-figma-kpis admin-figma-kpis--five admin-figma-kpis--lbf">
        @foreach ($stats as [$value, $label, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    @if ($selectedTeam)
        <section class="system-alert roster-status-banner">
            <div>
                <strong>Lista de Buena Fe {{ $selectedTeam->isRosterOpen() ? 'abierta' : 'cerrada' }}</strong>
                <span>{{ $selectedTeam->name }} · {{ $selectedTeam->tournament?->name }}</span>
                @if ($selectedTeam->registrationPeriodClosed())
                    <small>El período de inscripción del torneo ya cerró. El Delegado no puede modificar la lista libremente; AG/AT pueden reabrirla.</small>
                @endif
            </div>
            @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRole('admin-torneo'))
                <form method="post" action="{{ route('admin.players.roster') }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="team_id" value="{{ $selectedTeam->id }}">
                    <input type="hidden" name="roster_open" value="{{ $selectedTeam->isRosterOpen() ? 0 : 1 }}">
                    <button type="submit">{{ $selectedTeam->isRosterOpen() ? 'Cerrar lista' : 'Reabrir lista' }}</button>
                </form>
            @endif
        </section>
    @endif

    <section class="stc-card table-card team-list-panel player-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>{{ $rosterTitle }}</h3>
            <form method="post" action="{{ route('admin.players.validate', request()->query()) }}">
                @csrf
                <button class="admin-figma-validate" type="submit">Validar cumplimiento</button>
            </form>
        </header>
        <div class="stc-table admin-figma-table {{ $selectedTeam ? 'player-lbf-table' : 'player-table' }}">
            <div class="table-head">
                @if ($selectedTeam)
                    <span>#</span>
                @endif
                <span>Jugador</span>
                @if ($selectedTeam)
                    <span>Documento</span>
                    <span>Capa 1</span>
                    <span>Capa 2</span>
                    <span>Estado ficha</span>
                    <span>Documentación</span>
                    <span>Autorizaciones</span>
                    <span>Revisión</span>
                    <span>Habilitación</span>
                @else
                    <span>Delegación</span>
                    <span>Equipo</span>
                    <span>Categoría</span>
                    <span>Edad</span>
                    <span>Estado ficha</span>
                    <span>Documentación</span>
                    <span>Habilitación</span>
                @endif
                <span>Acción</span>
            </div>

            @forelse ($players as $player)
                @php
                    $fichaLabel = $player->fileStatusLabel();
                    $docsLabel = $player->documentationSummary();
                    $eligLabel = $player->eligibilityLabel();
                @endphp
                <div class="table-row">
                    @if ($selectedTeam)
                        <span>{{ $loop->iteration }}</span>
                    @endif
                    <span>
                        <x-entity-cell
                            :href="route('admin.players.show', $player)"
                            :src="$player->listPhotoUrl()"
                            :alt="$player->fullName()"
                            shape="round"
                        >
                            {{ $player->fullName() }}
                            @unless ($selectedTeam)
                                <x-slot:subtitle>{{ $player->formattedDocument() }}</x-slot:subtitle>
                            @endunless
                        </x-entity-cell>
                    </span>
                    @if ($selectedTeam)
                        <span>{{ $player->formattedDocument() ?: '—' }}</span>
                        <span>{{ $player->layerOneLabel() }}</span>
                        <span>{{ $player->layerTwoLabel() }}</span>
                        <span @class(['admin-status', 'is-observed' => $player->status === 'observed', 'is-approved' => in_array($player->status, ['approved', 'enabled'], true)])>{{ $fichaLabel }}</span>
                        <span>{{ $docsLabel }}</span>
                        <span>{{ $player->authorizationSummary() }}</span>
                        <span>{{ $player->reviewStatusLabel() }}</span>
                        <span @class(['admin-status', 'is-approved' => $player->status === 'enabled', 'is-blocked' => $player->status !== 'enabled'])>{{ $eligLabel }}</span>
                    @else
                        <span>
                            @if ($player->team?->delegation)
                                <x-entity-ref :href="route('admin.delegations.show', $player->team->delegation)" :src="$player->team->delegation->logoUrl()" :alt="$player->team->delegation->name">
                                    {{ $player->team->delegation->name }}
                                </x-entity-ref>
                            @else
                                {{ $player->team?->delegation_name ?? '—' }}
                            @endif
                        </span>
                        <span>
                            @if ($player->team)
                                <x-entity-ref :href="route('admin.teams.show', $player->team)" :src="$player->team->shieldUrl()" :alt="$player->team->name">
                                    {{ $player->team->name }}
                                </x-entity-ref>
                            @else
                                Sin equipo
                            @endif
                        </span>
                        <span>
                            @if ($player->team?->category)
                                <x-entity-ref :href="route('admin.categories.show', $player->team->category)" :src="$player->team->category->bannerUrl()" :alt="$player->team->category->name">
                                    {{ $player->team->category->name }}
                                </x-entity-ref>
                            @else
                                —
                            @endif
                        </span>
                        <span>{{ $player->age() ?? '—' }}</span>
                        <span @class(['admin-status', 'is-observed' => $player->status === 'observed', 'is-approved' => in_array($player->status, ['approved', 'enabled'], true)])>{{ $fichaLabel }}</span>
                        <span>{{ $docsLabel }}</span>
                        <span @class(['admin-status', 'is-approved' => $player->status === 'enabled', 'is-blocked' => $player->status !== 'enabled'])>{{ $eligLabel }}</span>
                    @endif
                    <span class="category-actions">
                        <a href="{{ route('admin.players.show', [$player, 'tab' => 'aprobacion']) }}">{{ in_array($player->status, ['enabled', 'approved'], true) ? 'Ver ficha' : 'Aprobar' }}</a>
                        <a href="{{ route('admin.players.edit', $player) }}">Editar</a>
                        <x-whatsapp-link :url="$player->whatsappShareUrl()" />
                    </span>
                </div>
            @empty
                <div class="table-row">
                    @if ($selectedTeam)
                        <span>—</span>
                    @endif
                    <span>
                        <strong>No hay jugadores con esos filtros.</strong>
                        <small>Cambiá filtros o cargá una ficha desde Nuevo.</small>
                    </span>
                    @for ($i = 0; $i < ($selectedTeam ? 8 : 7); $i++)
                        <span>-</span>
                    @endfor
                    <span class="category-actions">
                        <a href="{{ route('admin.players.index') }}">Ver todos</a>
                    </span>
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
