<article class="planilla-sheet">
    <header class="planilla-header">
        <table class="planilla-meta">
            <tr>
                <td class="label">Competición:</td>
                <td class="value">{{ $meta['competition'] }}</td>
                <td class="spacer"></td>
                <td class="label">Juego:</td>
                <td class="value" style="min-width:100px;">{{ $meta['juego'] }}</td>
            </tr>
            <tr>
                <td class="label">Categoría:</td>
                <td class="value">{{ $meta['category'] }}</td>
                <td class="spacer"></td>
                <td class="label">Fase:</td>
                <td class="value">{{ $meta['phase'] }}</td>
                <td class="spacer"></td>
                <td class="label">Ronda:</td>
                <td class="value">{{ $meta['round'] }}</td>
            </tr>
        </table>

        <div class="planilla-scoreline">
            <span class="team-home">{{ $meta['home_name'] }}</span>
            <span class="score-box">{{ $meta['home_score'] !== null && $meta['home_score'] !== '' ? $meta['home_score'] : '' }}</span>
            <span class="score-sep">x</span>
            <span class="score-box">{{ $meta['away_score'] !== null && $meta['away_score'] !== '' ? $meta['away_score'] : '' }}</span>
            <span class="team-away">{{ $meta['away_name'] }}</span>
        </div>

        <div class="planilla-site-row">
            <div>
                <table class="planilla-site-meta">
                    <tr>
                        <td class="label">Sítio:</td>
                        <td class="value">{{ $meta['venue'] }}</td>
                        <td class="label" style="padding-left:12px;">Fecha:</td>
                        <td class="value">{{ $meta['datetime'] }}</td>
                    </tr>
                </table>
                <div class="planilla-ref">
                    <span class="label">Arbitraje:</span>
                    <span class="line">{{ $meta['referee'] }}</span>
                </div>
            </div>

            <table class="planilla-periods">
                <thead>
                    <tr>
                        <th></th>
                        <th>Inicio</th>
                        <th>Final</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th>Período 1</th>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Período 2</th>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>Período Extra</th>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </header>

    @include('workspace.planillas.partials.team-block', ['team' => $home])
    @include('workspace.planillas.partials.team-block', ['team' => $away])
</article>
