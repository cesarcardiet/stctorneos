@php
    $matchScope = \App\Support\CategoryWorkspace::matchOrderScope(
        ($selectedPhase ?? 'all') !== 'all' ? $selectedPhase : null,
        ($selectedRound ?? 'all') !== 'all' ? $selectedRound : null
    );
    $matchScopeLabel = collect([
        ($selectedPhase ?? 'all') !== 'all' ? 'Fase '.$selectedPhase : 'Todas las fases',
        ($selectedRound ?? 'all') !== 'all' ? $selectedRound : 'Todas las fechas',
    ])->implode(' · ');
@endphp

<x-ws-modal id="reorder-teams" title="Reordenar equipos" :wide="true">
    <p class="ws-muted ws-plus-intro">Mantené presionado ⋮⋮ y arrastrá. El orden se guarda solo y afecta la tabla visible (no cambia los puntos).</p>

    @if ($groups->isEmpty())
        <p class="ws-muted">Todavía no hay equipos para ordenar.</p>
    @else
        <div class="ws-reorder-tabs">
            @foreach ($groups as $groupName => $rows)
                @php
                    $groupKey = str_starts_with($groupName, 'Grupo ') ? substr($groupName, strlen('Grupo ')) : ($groupName === 'Sin grupo' ? '' : $groupName);
                    $tabLabel = $groupName === 'Sin grupo' ? 'Clasificación general' : strtoupper($category->groupDisplayName($groupKey) ?: $groupName);
                @endphp
                <details class="ws-reorder-panel" @if ($loop->first) open @endif>
                    <summary>{{ $tabLabel }}</summary>
                    @if ($rows->isEmpty())
                        <p class="ws-muted">Sin equipos en este grupo.</p>
                    @else
                        <ul
                            class="ws-drag ws-reorder-list"
                            data-ws-reorder-teams
                            data-group-key="{{ $groupKey }}"
                            data-ws-reorder-url="{{ route('workspace.categories.teams.reorder', $category) }}"
                        >
                            @foreach ($rows as $row)
                                <li data-team-id="{{ $row['team']->id }}">
                                    <button type="button" class="ws-drag-handle" data-ws-drag aria-label="Mover {{ $row['team']->name }}">⋮⋮</button>
                                    <span>{{ $row['position'] }}. {{ $row['team']->name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </details>
            @endforeach
        </div>
    @endif
</x-ws-modal>

<x-ws-modal id="reorder-matches" title="Reordenar cruces" :wide="true">
    <p class="ws-muted ws-plus-intro">Recorte actual: <strong>{{ $matchScopeLabel }}</strong>. Arrastrá los emparejamientos para cambiar el orden en Clasificación y en la impresión.</p>

    @if ($visibleMatches->isEmpty())
        <p class="ws-muted">No hay partidos en este recorte. Cambiá fase o fecha en Clasificación e intentá de nuevo.</p>
    @else
        <ul
            class="ws-drag ws-reorder-list"
            data-ws-reorder-matches
            data-phase="{{ $selectedPhase ?? 'all' }}"
            data-round="{{ $selectedRound ?? 'all' }}"
            data-ws-reorder-url="{{ route('workspace.categories.matches.reorder', $category) }}"
        >
            @foreach ($visibleMatches as $match)
                <li data-match-id="{{ $match->id }}">
                    <button type="button" class="ws-drag-handle" data-ws-drag aria-label="Mover partido">⋮⋮</button>
                    <span>{{ $match->homeTeam?->name }} x {{ $match->awayTeam?->name }}</span>
                    <small>{{ \App\Support\CategoryWorkspace::normalizeRound($match->round, $match->stage) ?: 'Sin fecha' }}</small>
                </li>
            @endforeach
        </ul>
    @endif
</x-ws-modal>
