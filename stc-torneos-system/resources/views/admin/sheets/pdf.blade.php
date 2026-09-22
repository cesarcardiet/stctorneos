<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Planilla {{ $sheet->match?->title() }}</title>
    <style>
        body { margin: 24px; font-family: Inter, Arial, sans-serif; color: #111; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p, td, th { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        .actions { margin-top: 24px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <h1>Planilla oficial</h1>
    <p>{{ $sheet->match?->tournament?->name }} · {{ $sheet->match?->category?->name }}</p>
    <p>{{ $sheet->match?->title() }} · {{ $sheet->scoreLine() }} · {{ $sheet->matchDateLabel() }}</p>
    <p>Cancha: {{ $sheet->match?->field?->name }} · Árbitro: {{ $sheet->referee_name ?: $sheet->match?->refereeLabel() }} · Asistente: {{ $sheet->assistant_name ?: '—' }} · Carga: {{ $sheet->responsibleLabel() }}</p>

    <table>
        <thead><tr><th>Min</th><th>Tipo</th><th>Jugador</th><th>Equipo</th><th>Detalle</th></tr></thead>
        <tbody>
            @forelse ($sheet->events as $event)
                <tr>
                    <td>{{ $event->minuteLabel() }}</td>
                    <td>{{ $event->typeLabel() }}</td>
                    <td>{{ $event->player?->fullName() ?: '—' }}</td>
                    <td>{{ $event->team?->name ?: '—' }}</td>
                    <td>{{ $event->detail }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin eventos</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Incidencias</h2>
    <table>
        <thead><tr><th>Tipo</th><th>Equipo</th><th>Estado</th><th>Notas</th></tr></thead>
        <tbody>
            @forelse ($sheet->incidents as $incident)
                <tr>
                    <td>{{ $incident->type }}</td>
                    <td>{{ $incident->related_name ?: '—' }}</td>
                    <td>{{ $incident->statusLabel() }}</td>
                    <td>{{ $incident->notes }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin incidencias</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Firmas</h2>
    <table>
        <thead><tr><th>Rol</th><th>Nombre</th><th>Estado</th></tr></thead>
        <tbody>
            @foreach ($sheet->signatures as $signature)
                <tr>
                    <td>{{ $signature->role }}</td>
                    <td>{{ $signature->name }}</td>
                    <td>{{ $signature->signed ? 'Firmada' : 'Pendiente' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir / guardar PDF</button>
        <a href="{{ route('admin.sheets.cierre', $sheet) }}">Volver</a>
    </div>
</body>
</html>
