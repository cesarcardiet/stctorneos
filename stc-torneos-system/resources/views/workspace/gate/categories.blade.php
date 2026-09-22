<x-layouts.workspace

    :title="$tournament->name"

    :heading="$tournament->name"

    subheading="Elegí una categoría. Después cargás equipos y jugadores."

    :tournament="$tournament"

    active="Categorías"

>

    @php

        $totalTeams = $categories->sum('teams_count');

        $totalPlayers = $categories->sum('players_count');

        $totalMatches = $categories->sum('matches_count');

        $finishedMatches = $categories->sum('finished_matches_count');

        $branchCounts = $categories->countBy('branch');

    @endphp



    <header class="ws-tourney-bar">
        <div class="ws-tourney-bar-top">
            <a class="ws-tourney-back" href="{{ route('workspace.home') }}">← Torneos</a>
            <div class="ws-tourney-bar-actions">
                @if ($canEdit)
                    <a class="stc-pill-btn ghost" href="{{ route('admin.tournaments.edit', $tournament) }}">Editar torneo</a>
                    <button type="button" class="stc-pill-btn" data-ws-open="new-category">+ Categoría</button>
                @endif
                @if (! empty($canDeleteTournaments))
                    <form method="post" action="{{ route('workspace.tournaments.destroy', $tournament) }}" onsubmit="return confirm('¿Eliminar {{ $tournament->name }}? Se borran categorías, equipos y partidos de ese torneo.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="stc-pill-btn danger">Eliminar torneo</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="ws-tourney-bar-main">
            <img class="ws-tourney-logo" src="{{ $tournament->logoUrl() }}" alt="">
            <div class="ws-tourney-bar-copy">
                <div class="ws-tourney-badges">
                    <span @class(['ws-tourney-status', 'is-'.$tournament->status])>{{ $tournament->statusLabel() }}</span>
                    @if ($tournament->edition)
                        <span class="ws-tourney-edition">{{ $tournament->edition }}</span>
                    @endif
                </div>
                <h2>{{ $tournament->name }}</h2>
                <p class="ws-tourney-meta">{{ collect([$tournament->city, $tournament->venue_name])->filter()->implode(' · ') }} · {{ $tournament->starts_at?->format('d/m/Y') ?: '—' }} – {{ $tournament->ends_at?->format('d/m/Y') ?: '—' }}</p>
                <div class="ws-tourney-chips">
                    <span><b>{{ $categories->count() }}</b> cat.</span>
                    <span><b>{{ $totalTeams }}</b> equipos</span>
                    <span><b>{{ $totalPlayers }}</b> jugadores</span>
                    <span><b>{{ $finishedMatches }}/{{ $totalMatches }}</b> partidos</span>
                </div>
            </div>
            @if ($categories->isNotEmpty())
                <div class="ws-tourney-search">
                    <input type="search" data-ws-cat-search placeholder="Buscar categoría…" autocomplete="off" aria-label="Buscar categoría">
                </div>
            @endif
        </div>

        @if ($categories->isNotEmpty())
            <div class="ws-tourney-filters">
                <div class="ws-tourney-filter-group" data-ws-cat-branch>
                    <button type="button" class="ws-chip is-on" data-branch="">Todas</button>
                    @if (($branchCounts['Masculina'] ?? 0) > 0)
                        <button type="button" class="ws-chip" data-branch="Masculina">Masc</button>
                    @endif
                    @if (($branchCounts['Femenina'] ?? 0) > 0)
                        <button type="button" class="ws-chip" data-branch="Femenina">Fem</button>
                    @endif
                    @if (($branchCounts['Mixta'] ?? 0) > 0)
                        <button type="button" class="ws-chip" data-branch="Mixta">Mix</button>
                    @endif
                </div>
                <div class="ws-tourney-filter-group ws-tourney-az">
                    @include('workspace.partials.category-az', ['letters' => $letters ?? []])
                </div>
                <div class="ws-tourney-filter-meta">
                    <small data-ws-cat-count>{{ $categories->count() }} categorías</small>
                    @if ($canEdit)
                        <button type="button" class="ws-chip ws-chip-ghost" data-ws-cat-az-sort>Ordenar A–Z</button>
                        <small class="ws-drag-hint">Arrastrá ⋮⋮</small>
                    @endif
                </div>
            </div>
        @endif
    </header>

    <div class="ws-cat-board" data-ws-cat-board @if ($canEdit && $categories->isNotEmpty()) data-ws-sort-url="{{ route('workspace.tournaments.categories.reorder', $tournament) }}" @endif>



        <section class="ws-cat-grid" @if ($canEdit && $categories->isNotEmpty()) data-ws-sort-cats @endif>

            @forelse ($categories as $item)

                @php

                    $ws = $item->workspace();

                    $pendingPlayers = (int) $item->pending_players_count;

                    $pendingDocs = (int) ($item->pending_docs_count ?? 0);

                    $branchClass = match ($item->branch) {

                        'Femenina' => 'branch-fem',

                        'Mixta' => 'branch-mix',

                        default => 'branch-masc',

                    };

                    $matchProgress = $item->matches_count > 0

                        ? min(100, (int) round(($item->finished_matches_count / $item->matches_count) * 100))

                        : 0;

                @endphp

                <article

                    class="ws-cat-item ws-cat-item--{{ $branchClass }}"

                    data-ws-cat-item

                    data-id="{{ $item->id }}"

                    data-name="{{ $item->name }}"

                    data-branch="{{ $item->branch }}"

                    data-letter="{{ $item->sortLetter() }}"

                    data-search="{{ $item->searchHaystack() }}"

                >

                    @if ($canEdit)

                        <div class="ws-cat-actions">

                            @include('workspace.partials.edit-category-button', [
                                'editCategory' => $item,
                                'editTournament' => $tournament,
                                'editCategoryLabel' => 'Editar',
                            ])

                            <button type="button" class="ws-drag-handle" data-ws-drag aria-label="Mover {{ $item->name }}">⋮⋮</button>

                            <details class="ws-cat-menu">

                                <summary aria-label="Acciones de {{ $item->name }}">⋯</summary>

                                <div class="ws-cat-menu-list">

                                    <button

                                        type="button"

                                        data-ws-edit-cat

                                        data-action="{{ route('workspace.tournaments.categories.update', [$tournament, $item]) }}"

                                        data-name="{{ $item->name }}"

                                        data-birth-year="{{ $item->birth_year }}"

                                        data-branch="{{ $item->branch }}"

                                        data-modality="{{ $item->modality }}"

                                        data-format="{{ $item->competition_format }}"

                                        data-groups="{{ $item->groups_count }}"

                                        data-status="{{ $item->status }}"

                                        data-points-win="{{ $item->points_win }}"

                                        data-points-draw="{{ $item->points_draw }}"

                                        data-points-loss="{{ $item->points_loss }}"

                                        data-description="{{ $item->workspace()['description'] ?? '' }}"
                                        data-image-url="{{ $item->bannerUrl() }}"
                                        data-image-path="{{ $item->image_path }}"

                                    >Editar</button>

                                    <form method="post" action="{{ route('workspace.tournaments.categories.destroy', [$tournament, $item]) }}" onsubmit="return confirm('¿Eliminar {{ $item->name }}? Se borran equipos, jugadores y partidos de esa categoría.')">

                                        @csrf

                                        @method('DELETE')

                                        <button type="submit" class="is-danger">Eliminar categoría</button>

                                    </form>

                                </div>

                            </details>

                        </div>

                    @endif

                    <div class="ws-cat-card">

                        <header class="ws-cat-card-head">

                            <img src="{{ $item->bannerUrl() }}" alt="">

                            <div>

                                <a class="ws-cat-title-link" href="{{ route('workspace.categories.teams', $item) }}">{{ $item->name }}</a>

                                <em>{{ $item->statusLabel() }}</em>

                            </div>

                        </header>



                        <div class="ws-cat-tags">

                            <span class="ws-cat-tag {{ $branchClass }}">{{ $item->branch }}</span>

                            <span class="ws-cat-tag modality">{{ $item->modalityLabel() }}</span>

                            <span class="ws-cat-tag">{{ $item->birth_year }}</span>

                        </div>



                        <p class="ws-cat-format">{{ $item->competition_format ?: 'Sin formato' }}{{ $item->groups_count ? ' · '.$item->groups_count.' grupos' : '' }}</p>



                        <div class="ws-cat-stats">
                            <a class="ws-cat-stat-link" href="{{ route('workspace.categories.teams', $item) }}"><span>Equipos</span><b>{{ $item->teams_count }}</b></a>
                            <a class="ws-cat-stat-link" href="{{ route('workspace.categories.players', $item) }}"><span>Jugadores</span><b>{{ $item->players_count }}</b></a>
                            <div><span>habilitados</span><b>{{ $item->enabled_players_count }}</b></div>
                            <div><span>Partidos</span><b>{{ $item->finished_matches_count }}/{{ $item->matches_count }}</b></div>
                        </div>



                        @if ($item->matches_count > 0)

                            <div class="ws-cat-progress" aria-hidden="true">

                                <span style="width: {{ $matchProgress }}%"></span>

                            </div>

                        @endif



                        <footer class="ws-cat-card-foot">

                            <div class="ws-cat-alerts">

                                @if ($ws['registrations_open'])

                                    <span class="ws-cat-alert is-open">Inscripciones abiertas</span>

                                @else

                                    <span class="ws-cat-alert">Inscripciones cerradas</span>

                                @endif

                                @if ($pendingPlayers > 0)

                                    <span class="ws-cat-alert is-warn">{{ $pendingPlayers }} fichas pendientes</span>

                                @endif

                                @if ($pendingDocs > 0)

                                    <span class="ws-cat-alert is-warn">{{ $pendingDocs }} docs</span>

                                @endif

                            </div>

                            <div class="ws-cat-foot-actions">
                                <a class="stc-pill-btn" href="{{ route('workspace.categories.teams', $item) }}">Equipos</a>
                                <a class="stc-pill-btn ghost" href="{{ route('workspace.categories.players', $item) }}">Jugadores</a>
                            </div>

                        </footer>

                    </div>

                </article>

            @empty

                <div class="ws-empty ws-empty--tourney">

                    @if (($canEdit ?? false) === false && ($categories->isEmpty()))
                        <strong>Todavía no tenés categorías de tu club en este torneo.</strong>
                        <p>El administrador debe asignarte a una delegación/club. Cuando tu club tenga equipos en una categoría, las vas a ver acá para cargar jugadores y documentación.</p>
                    @else
                        <strong>Este torneo todavía no tiene categorías.</strong>
                        <p>Armá la categoría completa acá: datos, equipos, jugadores y partidos de grupo. Después entrás a operarla.</p>
                    @endif

                    @if ($canEdit)

                        <button type="button" class="ws-btn" data-ws-open="new-category">Crear categoría</button>

                    @endif

                </div>

            @endforelse

        </section>

    </div>



    @if ($canEdit)

        <x-ws-modal id="new-category" title="Nueva categoría" :setup="true">

            <x-ws-form :action="route('workspace.tournaments.categories.store', $tournament)" submit="Crear y entrar">

                <p class="ws-setup-kicker">Datos de la categoría</p>

                <div class="ws-setup-grid">

                    <label>Nombre <input type="text" name="name" placeholder="Ej: Sub 12 Masculino" required></label>

                    <label>Años / edad <input type="text" name="birth_year" placeholder="Ej: 2012" required></label>

                    <label>Rama

                        <select name="branch">

                            <option>Masculina</option>

                            <option>Femenina</option>

                            <option>Mixta</option>

                        </select>

                    </label>

                    <label>Modalidad

                        <select name="modality">

                            @foreach ($modalities as $modality)

                                <option value="{{ $modality }}" @selected($modality === 'Fútbol 11')>{{ $modality }}</option>

                            @endforeach

                        </select>

                    </label>

                    <label>Formato

                        <select name="competition_format">

                            @foreach ($formats as $format)

                                <option value="{{ $format }}" @selected($format === 'Grupos y finales')>{{ $format }}</option>

                            @endforeach

                        </select>

                    </label>

                    <label>Grupos <input type="number" name="groups_count" min="1" max="8" value="2"></label>

                </div>

                <label>Descripción <textarea name="description" rows="2" placeholder="Opcional"></textarea></label>

                <label>Reglas <textarea name="rules" rows="2" placeholder="Opcional"></textarea></label>



                <p class="ws-setup-kicker">Puntos y premios</p>

                <div class="ws-setup-grid">

                    <label>Victoria <input type="number" name="points_win" value="3"></label>

                    <label>Empate <input type="number" name="points_draw" value="1"></label>

                    <label>Derrota <input type="number" name="points_loss" value="0"></label>

                    <label>1º <input type="text" name="prize_first" value="Campeón"></label>

                    <label>2º <input type="text" name="prize_second" value="Subcampeón"></label>

                    <label>3º <input type="text" name="prize_third" value="Tercer puesto"></label>

                </div>



                <p class="ws-setup-kicker">Equipos y jugadores (opcional)</p>

                <p class="ws-muted">Dejá todo vacío para crear solo la categoría. Si cargás equipos acá, un jugador por línea; también podés agregarlos después desde Operación.</p>

                <div class="ws-setup-teams">
                    @for ($index = 0; $index < 2; $index++)
                        <article class="ws-setup-team">
                            <div class="ws-setup-grid">
                                <label>Equipo <input type="text" name="teams[{{ $index }}][name]" placeholder="Opcional"></label>
                                <label>Grupo <input type="text" name="teams[{{ $index }}][group]" placeholder="A" maxlength="2"></label>
                            </div>
                            <label>Jugadores
                                <textarea name="teams[{{ $index }}][players]" rows="4" placeholder="Un jugador por línea (opcional)"></textarea>
                            </label>
                        </article>
                    @endfor
                </div>

            </x-ws-form>

        </x-ws-modal>



        @include('workspace.partials.edit-category-modal', [
            'modalities' => $modalities,
            'formats' => $formats,
            'bannerLogos' => \App\Models\Category::bannerLogoChoicesForTournament($tournament->id),
            'defaultBanner' => $tournament->logoUrl(),
        ])

    @endif

</x-layouts.workspace>


