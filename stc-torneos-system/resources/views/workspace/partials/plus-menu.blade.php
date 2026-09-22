@php
    $hasMatches = ($matches ?? collect())->isNotEmpty();
@endphp

<x-ws-modal id="plus-menu" title="Gestionar categoría">
    <p class="ws-muted ws-plus-intro">Atajos de Clasificación para {{ $category->name }}. Todo aplica a esta categoría.</p>

    <div class="ws-plus-list">
        <p class="ws-plus-section">Plantel</p>
        <a href="{{ route('workspace.categories.teams', $category) }}">Equipos</a>
        <a href="{{ route('workspace.categories.players', $category) }}">Jugadores</a>
        <button type="button" data-ws-open="registrations">Abrir / cerrar inscripciones</button>
        <a href="{{ route('workspace.categories.inscriptions', $category) }}">Bandeja de inscripciones</a>

        <p class="ws-plus-section">Competencia</p>
        <button type="button" data-ws-open="groups">Grupos</button>
        <button type="button" data-ws-open="phases">Fases</button>
        <button type="button" data-ws-open="rounds">Fechas</button>
        <button type="button" data-ws-open="columns">Tabla · columnas visibles</button>
        <button type="button" data-ws-open="criteria">Criterios de clasificación</button>
        <button type="button" data-ws-open="sport">Ajustes 3 / 1 / 0</button>
        <button type="button" data-ws-open="result">Resaltar puestos</button>
        <button type="button" data-ws-open="reorder-teams">Reordenar equipos</button>
        <button type="button" data-ws-open="reorder-matches">Reordenar cruces</button>

        <p class="ws-plus-section">Documentación</p>
        <a href="{{ route('workspace.categories.documents', $category) }}">Revisión de fichas</a>

        <p class="ws-plus-section">Partidos</p>
        @if (! empty($canScheduleMatches))
            <button type="button" data-ws-open="generate-fixture">Generar partidos</button>
            @if ($hasMatches)
                <button type="button" data-ws-open="clear-fixture">Borrar todos los partidos</button>
            @endif
        @endif

        <p class="ws-plus-section">Salida</p>
        <button type="button" data-ws-open="print-standings">Imprimir clasificación</button>

        <p class="ws-plus-section">Más opciones</p>
        <a href="{{ route('workspace.categories.settings', $category) }}">Configuración completa</a>
    </div>
</x-ws-modal>

<x-ws-modal id="print-standings" title="Imprimir clasificación">
    <form class="ws-toolbar ws-filter-bar" method="get" action="{{ route('workspace.categories.standings.print', $category) }}" target="_blank">
        <label>Fase
            <select name="phase">
                <option value="all" @selected(($selectedPhase ?? 'all') === 'all')>Todas</option>
                @foreach ($phases ?? [] as $phase)
                    <option value="{{ $phase }}" @selected(($selectedPhase ?? 'all') === $phase)>{{ $phase }}</option>
                @endforeach
            </select>
        </label>
        <label>Fecha
            <select name="round">
                <option value="all" @selected(($selectedRound ?? 'all') === 'all')>Todas</option>
                @foreach ($rounds ?? [] as $round)
                    <option value="{{ $round }}" @selected(($selectedRound ?? 'all') === $round)>{{ $round }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="ws-btn">Abrir para imprimir</button>
    </form>
    <p class="ws-muted">Se abre en una pestaña nueva. Usá Ctrl+P o “Guardar como PDF” del navegador.</p>
</x-ws-modal>
