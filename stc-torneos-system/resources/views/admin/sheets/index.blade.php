<x-layouts.stc
    title="Planillas | STC Torneos"
    active="Planillas"
    heading="Planillas"
    :subheading="$subheading"
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

    <form class="category-filter-bar player-filter-bar sheets-filter-bar" method="get" action="{{ route('admin.sheets.index') }}">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar partido, árbitro o cancha">
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Estado: todos</option>
            @foreach (\App\Models\MatchSheet::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="tournament_id">
            <option value="">Todos los torneos</option>
            @foreach ($accessibleTournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.sheets.index') }}">Limpiar</a>
    </form>

    <section class="sheets-list-card admin-figma-panel">
        <h3>Planillas de partido</h3>
        <div class="delegation-figma-table sheets-figma-table admin-figma-table">
            <div class="table-head">
                <span>Partido</span>
                <span>Estado</span>
                <span>Responsable</span>
                <span>Validación</span>
                <span>Acción</span>
            </div>
            @forelse ($sheets as $sheet)
                <div class="table-row">
                    <span>
                        <x-match-teams :match="$sheet->match" />
                        <small>{{ $sheet->match?->field?->name }}</small>
                    </span>
                    <span>{{ $sheet->statusLabel() }}</span>
                    <span>{{ $sheet->responsibleLabel() }}</span>
                    <span>{{ $sheet->validationLabel() }}</span>
                    <span class="category-actions">
                        <a href="{{ $sheet->openRoute() }}">{{ $sheet->actionLabel() }}</a>
                        @if ($sheet->status === 'closed')
                            <a href="{{ route('admin.sheets.placa', $sheet) }}">Placa</a>
                        @endif
                    </span>
                </div>
            @empty
                <div class="category-empty-state"><strong>No hay planillas cargadas.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
