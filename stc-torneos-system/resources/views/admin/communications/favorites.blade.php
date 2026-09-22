<x-layouts.stc
    title="Favoritos | STC Torneos"
    active="Comunicaciones"
    heading="Favoritos de la app"
    subheading="Equipos y partidos que los usuarios marcan para seguir en la app móvil."
>
    <section class="admin-figma-kpis">
        @foreach ($stats as [$value, $label, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    @include('admin.communications.partials.tabs')

    <section class="results-rankings-grid">
        <article class="sheets-list-card admin-figma-panel">
            <h3>Equipos favoritos</h3>
            <div class="delegation-figma-table comms-fav-table">
                <div class="table-head">
                    <span>Usuario</span>
                    <span>Equipo</span>
                    <span>Categoría</span>
                </div>
                @forelse ($teams as $favorite)
                    <div class="table-row">
                        <span><strong>{{ $favorite->user?->name }}</strong></span>
                        <span>{{ $favorite->team?->name }}</span>
                        <span>{{ $favorite->team?->category?->name ?? '—' }}</span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>Nadie marcó equipos todavía.</strong></div>
                @endforelse
            </div>
        </article>

        <article class="sheets-list-card admin-figma-panel">
            <h3>Partidos favoritos</h3>
            <div class="delegation-figma-table comms-fav-table">
                <div class="table-head">
                    <span>Usuario</span>
                    <span>Partido</span>
                    <span>Fase</span>
                </div>
                @forelse ($matches as $favorite)
                    <div class="table-row">
                        <span><strong>{{ $favorite->user?->name }}</strong></span>
                        <span>{{ $favorite->match?->title() }}</span>
                        <span>{{ $favorite->match?->stage }}</span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>Nadie marcó partidos todavía.</strong></div>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.stc>
