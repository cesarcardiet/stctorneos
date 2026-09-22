<x-layouts.stc
    title="Tablas | STC Torneos"
    active="Resultados"
    heading="Tabla de posiciones"
    :subheading="($category?->tournament?->name ?? 'Torneo').' · '.($category?->name ?? 'Elegí una categoría').' · partidos finalizados y validados'"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @include('admin.results.partials.tabs')

    @if (! $category)
        <div class="category-empty-state"><strong>No hay categorías para armar la tabla.</strong></div>
    @else
        <div class="standings-stack results-standings">
            @forelse ($groups as $groupName => $rows)
                <article class="standings-card">
                    <header>
                        <span>{{ strtoupper($groupName) }}</span>
                        <small>{{ $category->name }} · {{ $category->points_win }} pts victoria</small>
                    </header>
                    <div class="standings-table">
                        <div class="table-head">
                            <span>Pos</span><span>Equipo</span><span>PTS</span><span>J</span><span>G</span><span>E</span><span>P</span><span>GF</span><span>GC</span><span>DIF</span><span>%</span><span>PE</span>
                        </div>
                        @forelse ($rows as $row)
                            <div class="table-row">
                                <span>{{ $row['position'] }}</span>
                                <span>
                                    <x-entity-cell
                                        :href="route('admin.teams.show', $row['team'])"
                                        :src="$row['team']->shieldUrl()"
                                        :alt="$row['team']->name"
                                    >
                                        {{ strtoupper($row['team']->name) }}
                                    </x-entity-cell>
                                </span>
                                <span>{{ $row['points'] }}</span>
                                <span>{{ $row['played'] }}</span>
                                <span>{{ $row['won'] }}</span>
                                <span>{{ $row['drawn'] }}</span>
                                <span>{{ $row['lost'] }}</span>
                                <span>{{ $row['gf'] }}</span>
                                <span>{{ $row['ga'] }}</span>
                                <span>{{ $row['gd'] }}</span>
                                <span>{{ $row['percent'] }}</span>
                                <span>{{ $row['pending'] }}</span>
                            </div>
                        @empty
                            <div class="table-row"><span>-</span><span><strong>Sin equipos en este grupo</strong></span><span>0</span><span>0</span><span>0</span><span>0</span><span>0</span><span>0</span><span>0</span><span>0</span><span>0</span><span>0</span></div>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="category-empty-state"><strong>Esta categoría todavía no tiene equipos para clasificar.</strong></div>
            @endforelse
        </div>
    @endif
</x-layouts.stc>
