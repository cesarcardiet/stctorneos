<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Planilla · {{ $meta['home_name'] }} vs {{ $meta['away_name'] }}</title>

    @include('workspace.planillas.partials.styles')

</head>

<body class="planilla-doc">

    @include('workspace.planillas.partials.sheet', compact('meta', 'home', 'away'))



    @unless ($pdfMode ?? false)

        <div class="planilla-actions no-print">

            <a class="accent" href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}">Descargar PDF</a>

            <button type="button" onclick="window.print()">Imprimir</button>

            <a class="ghost" href="{{ route('workspace.categories.planillas', $category) }}">Elegir planillas</a>

        </div>

    @endunless



    @if (request()->boolean('print'))

        <script>window.addEventListener('load', () => window.print());</script>

    @endif

</body>

</html>


