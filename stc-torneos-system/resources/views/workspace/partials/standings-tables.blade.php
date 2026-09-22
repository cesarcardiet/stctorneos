@if (! empty($canEdit))
    <p class="ws-muted ws-drag-hint">Arrastrá ⋮⋮ para reordenar equipos dentro del grupo{{ $groups->count() > 1 ? ', o soltá en otro grupo para moverlo' : '' }}.</p>
@endif

@php
    $preferredColumnOrder = ['pts', 'j', 'g', 'e', 'p', 'gf', 'gc', 'dif', 'percent', 'pe'];
    $orderedColumns = collect($preferredColumnOrder)
        ->filter(fn ($column) => in_array($column, $visibleColumns, true))
        ->merge(collect($visibleColumns)->reject(fn ($column) => in_array($column, $preferredColumnOrder, true)))
        ->values();
@endphp

<div class="ws-group-stack" @if (! empty($canEdit) && $groups->isNotEmpty()) data-ws-move-teams data-ws-teams-url="{{ route('workspace.categories.teams.group', $category) }}" @endif>
    @forelse ($groups as $groupName => $rows)
        @php
            $groupKey = str_starts_with($groupName, 'Grupo ') ? substr($groupName, strlen('Grupo ')) : ($groupName === 'Sin grupo' ? '' : $groupName);
        @endphp
        <article class="standings-card" data-group-key="{{ $groupKey }}">
            <header>
                <div>
                    <span class="ws-standing-kicker">Tabla de posiciones</span>
                    <span class="ws-group-title">
                        {{ strtoupper($category->groupDisplayName($groupKey) ?: $groupName) }}
                        @if (! empty($canEdit) && $groupKey !== '')
                            <x-ws-pencil open="groups" label="Editar nombre del grupo" />
                        @endif
                    </span>
                </div>
                <span class="ws-standing-team-count">{{ $rows->count() }} {{ $rows->count() === 1 ? 'equipo' : 'equipos' }}</span>
            </header>
            <div class="standings-scroll">
                <div
                    class="standings-table ws-table"
                    style="--st-cols: {{ max(1, $orderedColumns->count()) }}"
                    @if (! empty($canEdit) && $rows->isNotEmpty())
                        data-ws-standings-sort
                        data-group-key="{{ $groupKey }}"
                        data-ws-reorder-url="{{ route('workspace.categories.teams.reorder', $category) }}"
                    @endif
                >
                    <div class="table-head">
                        <span class="st-position">{{ $positionLabel ?? 'POS' }}</span>
                        <span class="st-team">{{ $teamColumnLabel ?? 'Equipo' }}</span>
                        @foreach ($orderedColumns as $column)
                            <span class="st-col st-col-{{ $column }}" title="{{ $columnLabels[$column] ?? $column }}">{{ $shortLabels[$column] ?? strtoupper($column) }}</span>
                        @endforeach
                    </div>
                    @foreach ($rows as $row)
                        @php
                            $pos = $row['position'];
                            $total = $rows->count();
                            $hl = $pos <= $highlightFirst ? 'is-first' : ($highlightLast > 0 && $pos > $total - $highlightLast ? 'is-last' : '');
                            $team = $row['team'];
                        @endphp
                        <div class="table-row {{ $hl }}" data-team-row data-team-id="{{ $team->id }}">
                            <span class="st-position"><b>{{ $pos }}</b></span>
                            <span class="ws-team-cell st-team">
                                @if (! empty($canEdit))
                                    <button type="button" class="ws-drag-handle" data-ws-drag-team aria-label="Mover {{ $team->name }}">⋮⋮</button>
                                @endif
                                <x-ws-team-mark :team="$team" :href="route('workspace.categories.teams.show', [$category, $team])" class="ws-team-link" />
                            </span>
                            @foreach ($orderedColumns as $column)
                                <span class="st-col st-col-{{ $column }}" data-label="{{ $shortLabels[$column] ?? strtoupper($column) }}">{{ match ($column) {
                                    'pts' => $row['points'],
                                    'j' => $row['played'],
                                    'g' => $row['won'],
                                    'e' => $row['drawn'],
                                    'p' => $row['lost'],
                                    'gf' => $row['gf'],
                                    'gc' => $row['ga'],
                                    'dif' => $row['gd'],
                                    'percent' => $row['percent'],
                                    'pe' => $row['pending'],
                                    default => '—',
                                } }}</span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            @if ($rows->isEmpty())
                <p class="ws-muted ws-empty-group">Todavía no hay equipos en este grupo.</p>
            @endif
        </article>
    @empty
        <div class="ws-empty">Todavía no hay equipos para armar la tabla.</div>
    @endforelse
</div>
