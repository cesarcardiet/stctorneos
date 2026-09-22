<x-layouts.stc
    title="Fair Play | STC Torneos"
    active="Resultados"
    heading="Fair Play"
    :subheading="($category?->tournament?->name ?? 'Torneo').' · '.($category?->name ?? 'Elegí una categoría').' · Copa Fair Play STC'"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @include('admin.results.partials.tabs')

    @if ($category)
        @php $weights = $category->fairPlayWeights(); @endphp
        <section class="sheets-list-card ws-fairplay-rules">
            <h3>Reglamento STC — ponderación de conducta</h3>
            <p class="login-copy">Sanción de adulto &gt; sanción de jugador. Menor puntaje = mejor comportamiento.</p>
            <div class="ws-fairplay-scale">
                @foreach ($rules as [$label, $points])
                    <div class="ws-fairplay-scale-row">
                        <span>{{ $label }}</span>
                        <strong>+{{ $points }}</strong>
                    </div>
                @endforeach
            </div>
            <details class="ws-fairplay-tiebreak">
                <summary>Criterios de desempate</summary>
                <ol>
                    @foreach ($tiebreakers as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ol>
            </details>
        </section>

        <form class="category-filter-bar" method="post" action="{{ route('admin.results.fairplay.update') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="category_id" value="{{ $category->id }}">
            <input type="hidden" name="tournament_id" value="{{ $filters['tournament_id'] }}">
            <label>Amarilla jugador <input type="number" name="fair_play_yellow" min="0" max="10" value="{{ $weights['yellow'] }}"></label>
            <label>Roja jugador <input type="number" name="fair_play_red" min="0" max="10" value="{{ $weights['red'] }}"></label>
            <label>Incidencia genérica <input type="number" name="fair_play_incident" min="0" max="10" value="{{ $weights['incident'] }}"></label>
            <button type="submit">Guardar tarjetas jugador</button>
        </form>
    @endif

    <section class="sheets-list-card">
        <h3>Disciplina por equipo</h3>
        <div class="delegation-figma-table results-fairplay-table">
            <div class="table-head">
                <span>Pos</span>
                <span>Equipo</span>
                <span>TA</span>
                <span>TR</span>
                <span>CT</span>
                <span>Fam.</span>
                <span>Tarde</span>
                <span>Pts FP</span>
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
                            {{ $row['team']->name }}
                        </x-entity-cell>
                    </span>
                    <span>{{ $row['yellows'] }}</span>
                    <span>{{ $row['reds'] }}</span>
                    <span>{{ $row['staff_yellow'] + $row['staff_expulsion'] + $row['adult_serious'] }}</span>
                    <span>{{ $row['family_misconduct'] + $row['family_expulsion'] }}</span>
                    <span>{{ $row['late_arrival'] }}</span>
                    <span><strong>{{ $row['points'] }}</strong></span>
                </div>
            @empty
                <div class="category-empty-state"><strong>No hay disciplina cargada para esta categoría.</strong></div>
            @endforelse
        </div>
        <p class="login-copy">TA/TR = jugadores · CT = cuerpo técnico · Fam. = padres/familiares</p>
    </section>
</x-layouts.stc>
