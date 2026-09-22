<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Placa {{ $post->title }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #010204; color: #fff; font-family: Inter, Arial, sans-serif; }
        .placa { width: min(92vw, 520px); padding: 48px 32px; border-radius: 24px; text-align: center; background: #03060c; outline: 1px solid rgba(5,107,255,.7); }
        img { width: 92px; height: auto; margin-bottom: 18px; }
        small { display: block; color: #00adff; letter-spacing: .12em; text-transform: uppercase; font-weight: 700; }
        h1 { margin: 18px 0 8px; font-size: 28px; }
        p { margin: 0; color: #dbe8f5; }
        button { margin-top: 28px; height: 44px; padding: 0 22px; border: 0; border-radius: 12px; color: #fff; font-weight: 700; background: linear-gradient(90deg, #056bff, #00c7ff); cursor: pointer; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <article class="placa">
        <img src="{{ asset('images/stc-logo.png') }}" alt="STC">
        <small>{{ $post->typeLabel() }}</small>
        <h1>{{ $post->title }}</h1>
        <p>{{ $post->summary ?: $post->body }}</p>
        <p>{{ $post->tournament?->name }}</p>
        <button type="button" onclick="window.print()">Imprimir / guardar PDF</button>
    </article>
</body>
</html>
