<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clasificación · {{ $category->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #fff; color: #111; padding: 1.5rem; }
        .ws-print-head { margin-bottom: 1.25rem; }
        .ws-print-head h1 { margin: 0 0 .35rem; font-size: 1.35rem; }
        .ws-print-head p { margin: 0; color: #444; }
        .ws-print-actions { margin: 1rem 0 1.5rem; }
        @media print {
            .ws-print-actions, .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body class="planilla-doc">
    <header class="ws-print-head">
        <h1>Clasificación · {{ $category->name }}</h1>
        <p>{{ $tournament->name }} · {{ $printScope }}</p>
        <p>Impreso {{ now()->format('d/m/Y H:i') }}</p>
    </header>

    <div class="ws-print-actions no-print">
        <button type="button" class="ws-btn" onclick="window.print()">Imprimir / Guardar PDF</button>
        <a class="ws-btn ghost" href="{{ route('workspace.categories.standings', array_filter(['category' => $category, 'phase' => $selectedPhase !== 'all' ? $selectedPhase : null, 'round' => $selectedRound !== 'all' ? $selectedRound : null])) }}">Volver a Clasificación</a>
    </div>

    @php
        $canEdit = false;
        $positionLabel = 'Pos';
        $teamColumnLabel = 'Equipos';
    @endphp

    @include('workspace.partials.standings-tables')

    @if ($visibleMatches->isNotEmpty())
        <section style="margin-top: 2rem;">
            <h2 style="font-size: 1rem; margin-bottom: .75rem;">Partidos del recorte</h2>
            <table style="width:100%; border-collapse: collapse; font-size: .85rem;">
                <thead>
                    <tr>
                        <th style="text-align:left; border-bottom:1px solid #ccc; padding:.35rem;">Fecha</th>
                        <th style="text-align:left; border-bottom:1px solid #ccc; padding:.35rem;">Local</th>
                        <th style="text-align:center; border-bottom:1px solid #ccc; padding:.35rem;">Resultado</th>
                        <th style="text-align:left; border-bottom:1px solid #ccc; padding:.35rem;">Visitante</th>
                        <th style="text-align:left; border-bottom:1px solid #ccc; padding:.35rem;">Horario</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($visibleMatches as $match)
                        <tr>
                            <td style="padding:.35rem; border-bottom:1px solid #eee;">{{ \App\Support\CategoryWorkspace::normalizeRound($match->round, $match->stage) ?: '—' }}</td>
                            <td style="padding:.35rem; border-bottom:1px solid #eee;">{{ $match->homeTeam?->name }}</td>
                            <td style="padding:.35rem; border-bottom:1px solid #eee; text-align:center;">
                                @if ($match->home_score === null && $match->away_score === null)
                                    vs
                                @else
                                    {{ $match->home_score ?? 0 }} : {{ $match->away_score ?? 0 }}
                                @endif
                            </td>
                            <td style="padding:.35rem; border-bottom:1px solid #eee;">{{ $match->awayTeam?->name }}</td>
                            <td style="padding:.35rem; border-bottom:1px solid #eee;">{{ $match->scheduled_at?->format('d/m H:i') ?: '—' }} · {{ $match->field?->name ?: 'Sin cancha' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</body>
</html>
