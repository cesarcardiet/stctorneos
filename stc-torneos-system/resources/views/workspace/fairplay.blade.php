<x-layouts.workspace
    :title="$title"
    heading="Clasificación"
    :subheading="$tournament->name.' · '.$category->name.' · Copa Fair Play STC'"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    @include('workspace.partials.competition-tabs', ['category' => $category, 'tab' => 'fairplay'])

    <article class="ws-card ws-fairplay-rules">
        <p class="stc-eyebrow">Principio STC</p>
        <p class="ws-muted">Menor puntaje = mejor comportamiento. Las sanciones a adultos (DT, cuerpo técnico, padres y familiares) pesan más que las de jugadores.</p>

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
    </article>

    <article class="ws-card">
        <p class="stc-eyebrow">Disciplina por equipo</p>
        <p class="ws-muted">Se calcula con planillas cerradas. Las penalizaciones de padres, cuerpo técnico y puntualidad se cargan en la planilla del partido.</p>

        <div class="standings-table ws-fairplay-table">
            <div class="table-head">
                <span>Pos</span>
                <span>Equipo</span>
                <span title="Tarjetas amarillas jugadores">TA</span>
                <span title="Tarjetas rojas jugadores">TR</span>
                <span title="Cuerpo técnico">CT</span>
                <span title="Padres / familiares">Fam.</span>
                <span title="Llegadas tarde">Tarde</span>
                <span>Pts</span>
            </div>
            @forelse ($rows as $row)
                <div @class(['table-row', 'is-podium' => $row['position'] <= 3])>
                    <span>{{ $row['position'] }}</span>
                    <span>
                        <x-ws-team-mark
                            :team="$row['team']"
                            :href="route('workspace.categories.teams.show', [$category, $row['team']])"
                        />
                    </span>
                    <span>{{ $row['yellows'] }}</span>
                    <span>{{ $row['reds'] }}</span>
                    <span>{{ $row['staff_yellow'] + $row['staff_expulsion'] + $row['adult_serious'] }}</span>
                    <span @class(['has-value' => ($row['family_misconduct'] + $row['family_expulsion']) > 0])>{{ $row['family_misconduct'] + $row['family_expulsion'] }}</span>
                    <span>{{ $row['late_arrival'] }}</span>
                    <span><strong>{{ $row['points'] }}</strong></span>
                </div>
            @empty
                <div class="ws-empty">Todavía no hay disciplina cargada para esta categoría.</div>
            @endforelse
        </div>

        <p class="ws-muted ws-fairplay-legend">TA/TR = tarjetas de jugadores · CT = cuerpo técnico · Fam. = padres/familiares · Tarde = puntualidad</p>
    </article>
</x-layouts.workspace>
