<x-layouts.stc
    title="Búsqueda | STC Torneos"
    active="Dashboard"
    heading="Búsqueda"
    :subheading="$term === '' ? 'Escribí un nombre, DNI, equipo, partido o aviso para buscar en todo el sistema.' : 'Resultados para «'.$term.'»'"
>
    @if ($term === '')
        <div class="category-empty-state"><strong>Usá el buscador de arriba y tocá Buscar. Recorre torneos, equipos, jugadores, fixture y el resto de módulos que tengas habilitados.</strong></div>
    @elseif ($groups === [])
        <div class="category-empty-state"><strong>No encontramos resultados para «{{ $term }}».</strong></div>
    @else
        <section class="admin-search-grid">
            @foreach ($groups as $group)
                <article class="sheets-list-card admin-figma-panel">
                    <h3>{{ $group['label'] }}</h3>
                    <div class="delegation-figma-table admin-search-table">
                        <div class="table-head">
                            <span>Resultado</span>
                            <span>Detalle</span>
                            <span>Acción</span>
                        </div>
                        @foreach ($group['items'] as $item)
                            <div class="table-row">
                                <span><strong>{{ $item['title'] }}</strong></span>
                                <span>{{ $item['meta'] }}</span>
                                <span class="delegation-row-actions">
                                    <a class="primary-action" href="{{ $item['href'] }}">Abrir</a>
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <a class="admin-search-more" href="{{ $group['href'] }}">Ver todos en {{ $group['label'] }}</a>
                </article>
            @endforeach
        </section>
    @endif
</x-layouts.stc>
