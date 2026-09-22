@php
    $selectedCategoryId = $filters['category_id'] !== ''
        ? $filters['category_id']
        : ($tab === 'index' ? '' : ($category->id ?? ''));
    $query = array_filter([
        'search' => $filters['search'] ?: null,
        'category_id' => $filters['category_id'] ?: ($tab === 'index' ? null : ($category->id ?? null)),
        'tournament_id' => $filters['tournament_id'] ?: null,
        'status' => ($filters['status'] ?? 'all') !== 'all' ? $filters['status'] : null,
    ]);
@endphp

<nav class="category-tabs results-tabs">
    <a href="{{ route('admin.results.index', $query) }}" @class(['active' => $tab === 'index'])>Resultados oficiales</a>
    <a href="{{ route('admin.results.standings', $query) }}" @class(['active' => $tab === 'standings'])>Tablas</a>
    <a href="{{ route('admin.results.brackets', $query) }}" @class(['active' => $tab === 'brackets'])>Cruces</a>
    <a href="{{ route('admin.results.fairplay', $query) }}" @class(['active' => $tab === 'fairplay'])>Fair Play</a>
    <a href="{{ route('admin.results.rankings', $query) }}" @class(['active' => $tab === 'rankings'])>Rankings</a>
    <a href="{{ route('admin.results.sanctions', $query) }}" @class(['active' => $tab === 'sanctions'])>Sanciones</a>
    <a href="{{ route('admin.results.rating', $query) }}" @class(['active' => $tab === 'rating'])>STC Rating</a>
    <a href="{{ route('admin.results.team', $query) }}" @class(['active' => $tab === 'team'])>Equipo de la Fecha</a>
</nav>

<form class="category-filter-bar results-filter-bar player-filter-bar" method="get" action="{{ request()->url() }}">
    <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar equipo, fase o cancha">
    <select name="tournament_id">
        <option value="">Todos los torneos</option>
        @foreach ($tournaments as $tournament)
            <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
        @endforeach
    </select>
    <select name="category_id">
        <option value="">{{ $tab === 'index' ? 'Todas las categorías' : 'Elegí una categoría' }}</option>
        @foreach ($categories as $item)
            <option value="{{ $item->id }}" @selected((string) $selectedCategoryId === (string) $item->id)>{{ $item->name }}</option>
        @endforeach
    </select>
    @if ($tab === 'index')
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Estado: todos</option>
            <option value="live" @selected($filters['status'] === 'live')>En juego</option>
            <option value="finished" @selected($filters['status'] === 'finished')>Finalizado</option>
            <option value="validated" @selected($filters['status'] === 'validated')>Validado</option>
        </select>
    @endif
    <button type="submit">Filtrar</button>
    <a href="{{ request()->url() }}">Limpiar</a>
</form>
