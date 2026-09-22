<x-layouts.stc
    title="Revisión de planteles | STC Torneos"
    active="Revisión"
    heading="Revisión"
    subheading="Categoría → equipo → jugadores. Desde acá se ve el tutor y se aprueba la ficha."
>
    <p class="ficha-flow">Elegí una categoría para ver sus equipos y el plantel.</p>

    <section class="stc-card table-card team-list-panel review-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Categorías</h3>
        </header>
        <div class="stc-table team-roster-table review-table admin-figma-table">
            <div class="table-head">
                <span>Categoría</span>
                <span>Torneo</span>
                <span>Equipos</span>
                <span></span>
            </div>
            @forelse ($categories as $category)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.review.category', $category)"
                            :src="$category->bannerUrl()"
                            :alt="$category->name"
                        >
                            {{ $category->name }}
                            <x-slot:subtitle>{{ $category->branch }} · {{ $category->modalityLabel() }}</x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>
                        @if ($category->tournament)
                            <x-entity-ref :href="route('admin.tournaments.show', $category->tournament)" :src="$category->tournament->logoUrl()" :alt="$category->tournament->name">
                                {{ $category->tournament->name }}
                            </x-entity-ref>
                        @endif
                    </span>
                    <span>{{ $category->teams_count }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.review.category', $category) }}">Ver equipos</a>
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span><strong>No hay categorías en tu alcance.</strong></span>
                    <span>-</span>
                    <span>0</span>
                    <span></span>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
