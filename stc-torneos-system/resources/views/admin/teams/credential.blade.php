<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Credencial {{ $staff->fullName() }} | STC Torneos</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #020714; color: #fff; }
        .credential-sheet {
            max-width: 420px;
            margin: 2.5rem auto;
            padding: 1.6rem;
            border: 1px solid rgba(0, 213, 255, .4);
            border-radius: 18px;
            background: rgba(5, 12, 24, .96);
            text-align: center;
        }
        .credential-sheet img { width: 110px; height: 110px; object-fit: cover; border-radius: 16px; }
        .credential-sheet h1 { font-size: 1.2rem; margin: 1rem 0 .35rem; }
        .credential-sheet p { margin: .25rem 0; color: #9db0c8; }
        .credential-actions { display: flex; justify-content: center; gap: 1rem; margin-top: 1.2rem; }
        .credential-actions a, .credential-actions button {
            color: #00d5ff; background: none; border: 0; font-weight: 800; cursor: pointer;
        }
        @media print {
            .credential-actions { display: none; }
            body { background: #fff; color: #111; }
        }
    </style>
</head>
<body>
    <article class="credential-sheet">
        <img src="{{ $staff->photoUrl() }}" alt="{{ $staff->fullName() }}">
        <h1>{{ $staff->fullName() }}</h1>
        <p>{{ $staff->roleLabel() }}</p>
        <p>{{ $team->name }} · {{ $team->category?->name }}</p>
        <p>{{ $team->tournament?->name }}</p>
        <p>{{ $staff->formattedDocument() }}</p>
        @isset($qrUrl)
            <img src="{{ $qrUrl }}" alt="QR credencial" width="160" height="160">
        @endisset
        <div class="credential-actions">
            <button type="button" onclick="window.print()">Imprimir credencial</button>
            <a href="{{ route('admin.teams.show', $team) }}">Volver</a>
        </div>
    </article>
</body>
</html>
