<x-layouts.workspace
    :title="$title"
    heading="Planillas en blanco"
    :subheading="$category->name.' · Elegí los partidos a imprimir'"
    :category="$category"
    :tournament="$tournament"
    active="Fixture"
>
    <article class="ws-card ws-planilla-picker">
        <p class="ws-muted">Cada planilla trae los dos equipos en columnas separadas, con plantel, goles, sustituciones y espacio para anotar en cancha.</p>

        <form class="ws-toolbar ws-filter-bar" method="get" action="{{ route('workspace.categories.planillas', $category) }}">
            <label>
                Fase
                <select name="phase" onchange="this.form.submit()">
                    <option value="all" @selected($selectedPhase === 'all')>Todas</option>
                    @foreach ($phases as $phase)
                        <option value="{{ $phase }}" @selected($selectedPhase === $phase)>{{ $phase }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Fecha
                <select name="round" onchange="this.form.submit()">
                    <option value="all" @selected($selectedRound === 'all')>Todas</option>
                    @foreach ($rounds as $round)
                        <option value="{{ $round }}" @selected($selectedRound === $round)>{{ $round }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Estado
                <select name="status" onchange="this.form.submit()">
                    <option value="all" @selected($selectedStatus === 'all')>Todos</option>
                    <option value="pending" @selected($selectedStatus === 'pending')>Sin jugar</option>
                    <option value="scheduled" @selected($selectedStatus === 'scheduled')>Solo programados</option>
                    <option value="finished" @selected($selectedStatus === 'finished')>Finalizados</option>
                </select>
            </label>
            @if ($selectedPhase !== 'all' || $selectedRound !== 'all' || $selectedStatus !== 'all')
                <a class="ws-btn ghost" href="{{ route('workspace.categories.planillas', $category) }}">Limpiar filtros</a>
            @endif
        </form>

        @if ($matches->isEmpty() && ($totalMatches ?? 0) > 0)
            <p class="ws-planilla-picker-alert">
                Hay {{ $totalMatches }} partido(s) en la categoría, pero ninguno coincide con el filtro
                @if ($selectedStatus === 'pending')
                    <strong>Sin jugar</strong> (puede que ya estén finalizados).
                @else
                    seleccionado.
                @endif
                <a href="{{ route('workspace.categories.planillas', array_filter(['category' => $category, 'status' => 'all'])) }}">Ver todos los partidos</a>
            </p>
        @endif

        <form method="get" action="{{ route('workspace.categories.planillas.download', $category) }}" target="_blank" id="planilla-download-form">
            <input type="hidden" name="phase" value="{{ $selectedPhase }}">
            <input type="hidden" name="round" value="{{ $selectedRound }}">
            <input type="hidden" name="status" value="{{ $selectedStatus }}">

            <div class="ws-planilla-picker-table-wrap">
                <table class="ws-planilla-picker-table">
                    <thead>
                        <tr>
                            <th class="check-col">
                                <input type="checkbox" id="planilla-select-all" @checked($matches->isNotEmpty()) aria-label="Seleccionar todos">
                            </th>
                            <th>Partido</th>
                            <th>Fecha</th>
                            <th>Cancha</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matches as $match)
                            <tr>
                                <td class="check-col">
                                    <input
                                        type="checkbox"
                                        name="matches[]"
                                        value="{{ $match->id }}"
                                        class="planilla-match-check"
                                        checked
                                        aria-label="Incluir {{ $match->homeTeam?->name }} vs {{ $match->awayTeam?->name }}"
                                    >
                                </td>
                                <td>
                                    <strong>{{ $match->homeTeam?->name }}</strong>
                                    <span class="ws-planilla-vs">vs</span>
                                    <strong>{{ $match->awayTeam?->name }}</strong>
                                    @if ($match->zoneLabel())
                                        <small>{{ $match->zoneLabel() }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ \App\Support\CategoryWorkspace::normalizeRound($match->round, $match->stage) ?: '—' }}
                                    <small>{{ $match->scheduled_at?->format('d/m H:i') ?: 'Sin horario' }}</small>
                                </td>
                                <td>{{ $match->field?->name ?: '—' }}</td>
                                <td>{{ $match->statusLabel() }}</td>
                                <td class="actions-col">
                                    <a href="{{ route('workspace.categories.matches.planilla', [$category, $match]) }}" target="_blank" rel="noopener">Ver una</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="ws-muted ws-planilla-picker-empty">No hay partidos con esos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="ws-planilla-picker-actions">
                <button type="submit" class="ws-btn" @disabled($matches->isEmpty())>
                    Descargar planillas seleccionadas ({{ $matches->count() }})
                </button>
                <a class="ws-btn ghost" href="{{ route('workspace.categories.fixture', $category) }}">Volver al fixture</a>
            </div>
        </form>
    </article>

    <script>
        const selectAll = document.getElementById('planilla-select-all');
        const checks = () => [...document.querySelectorAll('.planilla-match-check')];

        selectAll?.addEventListener('change', () => {
            checks().forEach((input) => {
                input.checked = selectAll.checked;
            });
        });

        document.getElementById('planilla-download-form')?.addEventListener('submit', (event) => {
            if (checks().some((input) => input.checked)) {
                return;
            }
            event.preventDefault();
            alert('Marcá al menos un partido para descargar.');
        });
    </script>
</x-layouts.workspace>
