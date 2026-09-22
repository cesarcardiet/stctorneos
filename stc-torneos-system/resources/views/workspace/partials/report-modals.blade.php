@php

    $teams = $category->teams ?? collect();

    $columnOptions = $columnLabels ?? \App\Support\CategoryWorkspace::columnLabels();

    $defaultColumns = $workspace['visible_columns'] ?? ['pts', 'j', 'g', 'e', 'p'];

    $matches = \App\Models\FixtureMatch::query()

        ->where('category_id', $category->id)

        ->orderBy('scheduled_at')

        ->get();

    $workspaceConfig = $category->workspace();

    $phases = \App\Support\CategoryWorkspace::phaseNames($workspaceConfig);

    $rounds = \App\Support\CategoryWorkspace::roundOptions(

        $workspaceConfig,

        $matches->map(fn ($match) => \App\Support\CategoryWorkspace::normalizeRound($match->round, $match->stage))->filter()->unique()->values()->all()

    );

@endphp



<article class="ws-card">

    <p class="stc-eyebrow">Imprimir reportes</p>

    <p class="ws-muted">Descargá PDF listo para imprimir o abrí la vista previa en el navegador.</p>

    <div class="ws-edit-rows">

        <div><span>Equipos (tabla de posiciones)</span> <button type="button" data-ws-open="report-teams">Generar</button></div>

        <div><span>Jugadores por equipo</span> <button type="button" data-ws-open="report-players">Generar</button></div>

        <div><span>Carnet masivo</span> <button type="button" data-ws-open="report-credentials">Generar</button></div>

        <div><span>Acta de juego</span> <button type="button" data-ws-open="report-acta">Generar</button></div>

    </div>

</article>



<x-ws-modal id="report-teams" title="Reporte de equipos">

    <form class="ws-form" method="get" action="{{ route('workspace.categories.reports.teams', $category) }}" target="_blank">

        <p class="ws-muted">Elegí las columnas del listado (como los checks del modal viejo).</p>

        <div class="ws-poll-flags">

            @foreach ($columnOptions as $key => $label)

                <label>

                    <input type="checkbox" name="columns[]" value="{{ $key }}" @checked(in_array($key, $defaultColumns, true))>

                    {{ $label }}

                </label>

            @endforeach

        </div>

        <div class="ws-form-actions">

            <button type="submit" class="ws-btn ghost">Ver e imprimir</button>

            <button type="submit" class="ws-btn" name="format" value="pdf">Descargar PDF</button>

        </div>

    </form>

</x-ws-modal>



<x-ws-modal id="report-players" title="Reporte de jugadores">

    <form class="ws-form" method="get" action="{{ route('workspace.categories.reports.players', $category) }}" target="_blank">

        <p class="ws-muted">Seleccioná uno o más equipos.</p>

        <div class="ws-poll-flags">

            @foreach ($teams as $team)

                <label>

                    <input type="checkbox" name="team_ids[]" value="{{ $team->id }}" checked>

                    {{ $team->name }} ({{ $team->players_count ?? $team->players()->count() }})

                </label>

            @endforeach

        </div>

        <div class="ws-form-actions">

            <button type="submit" class="ws-btn ghost">Ver e imprimir</button>

            <button type="submit" class="ws-btn" name="format" value="pdf">Descargar PDF</button>

        </div>

    </form>

</x-ws-modal>



<x-ws-modal id="report-credentials" title="Carnet masivo">

    <form class="ws-form" method="get" action="{{ route('workspace.categories.reports.credentials', $category) }}" target="_blank">

        <p class="ws-muted">Credenciales en grilla (nombre, equipo, nacimiento).</p>

        <div class="ws-poll-flags">

            @foreach ($teams as $team)

                <label>

                    <input type="checkbox" name="team_ids[]" value="{{ $team->id }}" checked>

                    {{ $team->name }}

                </label>

            @endforeach

        </div>

        <div class="ws-form-actions">

            <button type="submit" class="ws-btn ghost">Ver e imprimir</button>

            <button type="submit" class="ws-btn" name="format" value="pdf">Descargar PDF</button>

        </div>

    </form>

</x-ws-modal>



<x-ws-modal id="report-acta" title="Acta de juego">

    <form class="ws-form" method="get" action="{{ route('workspace.categories.reports.acta', $category) }}" target="_blank">

        <p class="ws-muted">Elegí fase y fecha, luego descargá cada acta desde el listado.</p>

        <label>Fase

            <select name="phase">

                <option value="all">Todas</option>

                @foreach ($phases as $phase)

                    <option value="{{ $phase }}">{{ $phase }}</option>

                @endforeach

            </select>

        </label>

        <label>Fecha / ronda

            <select name="round">

                <option value="all">Todas</option>

                @foreach ($rounds as $round)

                    <option value="{{ $round }}">{{ $round }}</option>

                @endforeach

            </select>

        </label>

        <div class="ws-form-actions">

            <button type="submit" class="ws-btn">Ver partidos</button>

        </div>

    </form>

    <p class="ws-muted">También podés ir a <a href="{{ route('workspace.categories.planillas', $category) }}">Planillas</a> para descargar varias juntas.</p>

</x-ws-modal>


