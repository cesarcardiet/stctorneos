<x-layouts.stc
    title="STC Rating | STC Torneos"
    active="Resultados"
    heading="STC Rating"
    :subheading="$subheading"
>
    @include('admin.results.partials.tabs')

    <form class="category-filter-bar results-filter-bar" method="get" action="{{ route('admin.results.rating') }}">
        <input type="hidden" name="category_id" value="{{ $category?->id }}">
        <input type="hidden" name="tournament_id" value="{{ $filters['tournament_id'] }}">
        <select name="round">
            <option value="">Todas las fechas</option>
            @foreach ($rounds as $item)
                <option value="{{ $item }}" @selected($round === (string) $item)>{{ $item }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
    </form>

    <section class="sheets-list-card">
        <h3>{{ $category?->name ?? 'Elegí una categoría' }}</h3>
        <div class="delegation-figma-table">
            <div class="table-head">
                <span>#</span>
                <span>Jugador</span>
                <span>Equipo</span>
                <span>Puntos</span>
                <span>Detalle</span>
            </div>
            @forelse ($rows as $row)
                <div class="table-row">
                    <span>{{ $row['position'] }}</span>
                    <span><strong>{{ $row['player']->fullName() }}</strong></span>
                    <span>{{ $row['team'] }}</span>
                    <span>{{ $row['points'] }}</span>
                    <span>
                        @foreach ($row['breakdown'] as $key => $value)
                            {{ $key }} {{ $value }}{{ $loop->last ? '' : ' · ' }}
                        @endforeach
                    </span>
                </div>
            @empty
                <div class="category-empty-state"><strong>Todavía no hay rating. Cargá eventos en las planillas cerradas.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
