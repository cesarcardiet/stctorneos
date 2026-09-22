<x-layouts.stc
    title="Auditoría | STC Torneos"
    active="Auditoría"
    heading="Auditoría"
    :subheading="$subheading"
>
    <section class="admin-figma-kpis">
        @foreach ($stats as [$value, $label, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <form class="category-filter-bar player-filter-bar audit-filter-bar" method="get" action="{{ route('admin.audit.index') }}">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar en auditoría">
        <select name="module">
            <option value="all">Módulo: todos</option>
            @foreach ($modules as $module)
                <option value="{{ $module }}" @selected($filters['module'] === $module)>{{ $module }}</option>
            @endforeach
        </select>
        <select name="user_id">
            <option value="">Usuario: todos</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected((string) $filters['user_id'] === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.audit.index') }}">Limpiar</a>
    </form>

    <section class="sheets-list-card admin-figma-panel">
        <h3>Registro de auditoría</h3>
        <div class="delegation-figma-table audit-figma-table admin-figma-table">
            <div class="table-head">
                <span>Usuario</span>
                <span>Rol</span>
                <span>Acción</span>
                <span>Registro afectado</span>
                <span>Fecha/hora</span>
            </div>
            @forelse ($logs as $log)
                <a class="table-row" href="{{ route('admin.audit.show', $log) }}">
                    <span><strong>{{ $log->user?->name ?? 'Sistema' }}</strong></span>
                    <span>{{ $log->user?->roleLabel() ?? 'Sin rol' }}</span>
                    <span>{{ $log->description ?: $log->actionLabel() }}</span>
                    <span>{{ $log->recordLabel() }}</span>
                    <span>{{ $log->happenedAtLabel() }}</span>
                </a>
            @empty
                <div class="category-empty-state"><strong>No hay eventos de auditoría con ese filtro.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
