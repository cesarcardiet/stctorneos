<x-layouts.stc
    title="Comunicaciones | STC Torneos"
    active="Comunicaciones"
    heading="Comunicaciones"
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

    @include('admin.communications.partials.tabs')

    <form class="category-filter-bar player-filter-bar" method="get" action="{{ route('admin.communications.index') }}">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar título o comunicado">
        <select name="type">
            <option value="all">Tipo: todos</option>
            @foreach (\App\Models\ContentPost::typeLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.communications.index') }}">Limpiar</a>
        <a href="{{ route('admin.communications.create') }}">Nuevo contenido</a>
    </form>

    <section class="sheets-list-card admin-figma-panel">
        <h3>Contenido oficial</h3>
        <div class="delegation-figma-table comms-figma-table admin-figma-table">
            <div class="table-head">
                <span>Título</span>
                <span>Tipo</span>
                <span>Audiencia</span>
                <span>Estado</span>
                <span>Acción</span>
            </div>
            @forelse ($posts as $post)
                <div class="table-row">
                    <span>
                        <strong>{{ $post->title }}</strong>
                        <small>{{ $post->tournament?->name ?? 'Todos los torneos' }} · {{ $post->author?->name ?? 'STC' }}</small>
                    </span>
                    <span>{{ $post->typeLabel() }}</span>
                    <span>{{ $post->audienceLabel() }}</span>
                    <span>{{ $post->statusLabel() }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.communications.show', $post) }}">Ver</a>
                        <a href="{{ route('admin.communications.edit', $post) }}">Editar</a>
                    </span>
                </div>
            @empty
                <div class="category-empty-state"><strong>No hay contenido con ese filtro.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
