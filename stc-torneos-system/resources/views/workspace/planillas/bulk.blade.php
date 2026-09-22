<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Planillas · {{ $category->name }} ({{ $count ?? $planillas->count() }})</title>
    @include('workspace.planillas.partials.styles')
</head>
<body class="planilla-doc">
    @forelse ($planillas as $planilla)
        <section class="planilla-page">
            @include('workspace.planillas.partials.sheet', [
                'meta' => $planilla['meta'],
                'home' => $planilla['home'],
                'away' => $planilla['away'],
            ])
        </section>
    @empty
        <p>No hay partidos en este recorte para generar planillas.</p>
    @endforelse

    <div class="planilla-actions no-print">
        <button type="button" onclick="window.print()">Descargar {{ $count ?? $planillas->count() }} planilla(s) · PDF</button>
        <a class="ghost" href="{{ route('workspace.categories.planillas', $category) }}">Elegir otros partidos</a>
    </div>

    @if (request()->boolean('print'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
