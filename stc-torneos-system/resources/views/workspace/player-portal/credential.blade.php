<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Credencial {{ $player->fullName() }} | STC Torneos</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #020714; color: #fff; }
        .credential-sheet { max-width: 420px; margin: 2.5rem auto; padding: 1.6rem; border: 1px solid rgba(0, 213, 255, .4); border-radius: 18px; background: rgba(5, 12, 24, .96); text-align: center; }
        .credential-sheet img { width: 110px; height: 110px; object-fit: cover; border-radius: 16px; }
        .credential-sheet h1 { font-size: 1.2rem; margin: 1rem 0 .35rem; }
        .credential-sheet p { margin: .25rem 0; color: #9db0c8; }
        .credential-actions { display: flex; justify-content: center; gap: 1rem; margin-top: 1.2rem; flex-wrap: wrap; }
        .credential-actions a, .credential-actions button { color: #00d5ff; background: none; border: 0; font-weight: 800; cursor: pointer; text-decoration: none; }
        @media print { .credential-actions { display: none; } body { background: #fff; color: #111; } }
    </style>
</head>
<body>
    <article class="credential-sheet">
        <img src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
        <h1>{{ $player->fullName() }}</h1>
        <p>Jugador · N° {{ $player->jersey_number ?: '—' }}</p>
        <p>{{ $player->team?->name }} · {{ $player->team?->category?->name }}</p>
        <p>{{ $player->team?->tournament?->name }}</p>
        <p>{{ $player->formattedDocument() }}</p>
        <img src="{{ $qrUrl }}" alt="QR credencial" width="160" height="160">
        <div class="credential-actions">
            <button type="button" onclick="window.print()">Imprimir credencial</button>
            <a href="{{ route('workspace.player.home') }}">Volver a Mi ficha</a>
        </div>
    </article>
</body>
</html>
