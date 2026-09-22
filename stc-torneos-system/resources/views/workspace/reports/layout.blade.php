<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title') · STC Torneos</title>

    <style>

        :root { color-scheme: light; }

        * { box-sizing: border-box; }

        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #111; margin: 0; padding: 1rem 1.15rem; background: #fff; font-size: 11px; }

        .report-brand { border-bottom: 2px solid #111; padding-bottom: .45rem; margin-bottom: .85rem; }

        .report-brand strong { display: block; font-size: 13px; letter-spacing: .06em; text-transform: uppercase; }

        .report-brand span { color: #444; font-size: 10px; }

        h1 { margin: 0 0 .25rem; font-size: 16px; }

        h2 { margin: 1rem 0 .45rem; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #333; }

        p.meta { margin: 0 0 .85rem; color: #555; font-size: 10px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: .85rem; font-size: 10px; }

        th, td { border: 1px solid #888; padding: 4px 5px; text-align: left; vertical-align: top; }

        th { background: #ececec; font-weight: 700; }

        tr:nth-child(even) td { background: #fafafa; }

        .ws-report-actions { margin: 1rem 0 1.5rem; display: flex; gap: .75rem; flex-wrap: wrap; }

        .ws-report-actions a, .ws-report-actions button {

            font: inherit; font-weight: 700; cursor: pointer; border: 1px solid #222; background: #fff; padding: .45rem .75rem; text-decoration: none; color: #111;

        }

        .ws-report-actions .primary { background: #111; color: #fff; border-color: #111; }

        .ws-report-actions .accent { background: #0b5cab; color: #fff; border-color: #0b5cab; }

        @media print {

            .no-print { display: none !important; }

            body { padding: 0; }

        }

        @yield('extra-styles')

    </style>

</head>

<body>

    <div class="report-brand">

        <strong>STC Torneos</strong>

        <span>{{ $tournament->name ?? 'Torneo' }} · {{ $category->name ?? 'Categoría' }}</span>

    </div>



    <header>

        <h1>@yield('heading')</h1>

        <p class="meta">Generado el {{ now()->format('d/m/Y H:i') }}</p>

    </header>



    @yield('content')



    @unless ($pdfMode ?? false)

        <div class="ws-report-actions no-print">

            <a class="accent" href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}">Descargar PDF</a>

            <button type="button" class="primary" onclick="window.print()">Imprimir</button>

            <a href="{{ route('workspace.categories.settings', $category) }}">Volver a Configuración</a>

        </div>

    @endunless



    @if (request()->boolean('print'))

        <script>window.addEventListener('load', () => window.print());</script>

    @endif

</body>

</html>


