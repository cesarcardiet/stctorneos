<x-layouts.stc
    title="Resultados | STC Torneos"
    active="Resultados"
    heading="Resultados"
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

    @include('admin.results.partials.tabs')

    <form class="results-toolbar" method="post" action="{{ route('admin.results.publish', array_filter($filters)) }}" data-confirm="¿Publicar en la app los resultados oficiales filtrados?">
        @csrf
        @method('PATCH')
        <p>Se publican partidos finalizados o validados. Los que están en juego se ven como parciales.</p>
        <button type="submit">Publicar en app</button>
    </form>

    <section class="sheets-list-card admin-figma-panel">
        <h3>Resultados oficiales</h3>
        <div class="delegation-figma-table results-figma-table admin-figma-table">
            <div class="table-head">
                <span>Partido</span>
                <span>Marcador</span>
                <span>Estado</span>
                <span>Publicación</span>
                <span>Acción</span>
            </div>
            @forelse ($matches as $match)
                <div class="table-row">
                    <span>
                        <x-match-teams :match="$match" />
                    </span>
                    <span class="results-score">{{ $match->scoreLine() }}</span>
                    <span class="status-text status-{{ $match->status }}">{{ $match->statusLabel() }}</span>
                    <span>{{ $match->published ? 'En app' : 'Interno' }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.fixture.show', $match) }}">Ver</a>
                        @if ($match->sheet && auth()->user()?->hasPermission('match_sheets.manage'))
                            <a href="{{ route('admin.sheets.cierre', $match->sheet) }}">Planilla</a>
                        @endif
                        @if (in_array($match->status, ['finished', 'validated'], true) && ! $match->published)
                            <form method="post" action="{{ route('admin.results.publish.match', $match) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit">Publicar</button>
                            </form>
                        @elseif (in_array($match->status, ['finished', 'validated'], true) && $match->published)
                            <form method="post" action="{{ route('admin.results.observe', $match) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit">Observar</button>
                            </form>
                        @endif
                    </span>
                </div>
            @empty
                <div class="category-empty-state"><strong>No hay resultados cargados con ese filtro.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
