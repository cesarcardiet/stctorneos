<x-layouts.stc
    :title="'Equipos · '.$category->name.' | STC Torneos'"
    active="Revisión"
    heading="Revisión"
    :subheading="$category->name.' · '.$category->tournament?->name"
>
    <a class="back-link" href="{{ route('admin.review.index') }}">← Categorías</a>
    <p class="ficha-flow">{{ $category->name }} → equipos. Entrá a un equipo para ver el listado de jugadores.</p>

    <section class="stc-card table-card team-list-panel review-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Equipos</h3>
        </header>
        <div class="stc-table team-roster-table review-table admin-figma-table">
            <div class="table-head">
                <span>Equipo</span>
                <span>Delegación</span>
                <span>Jugadores</span>
                <span></span>
            </div>
            @forelse ($category->teams as $team)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.review.team', [$category, $team])"
                            :src="$team->shieldUrl()"
                            :alt="$team->name"
                        >
                            {{ $team->name }}
                            <x-slot:subtitle>{{ $team->statusLabel() }}</x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>
                        @if ($team->delegation)
                            <x-entity-ref :href="route('admin.delegations.show', $team->delegation)" :src="$team->delegation->logoUrl()" :alt="$team->delegation->name">
                                {{ $team->delegation->name }}
                            </x-entity-ref>
                        @else
                            {{ $team->delegation_name ?? $team->city ?? '—' }}
                        @endif
                    </span>
                    <span>{{ $team->players_count }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.review.team', [$category, $team]) }}">Ver jugadores</a>
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span><strong>Esta categoría todavía no tiene equipos.</strong></span>
                    <span>-</span>
                    <span>0</span>
                    <span></span>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
