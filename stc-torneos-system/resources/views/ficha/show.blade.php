<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ficha del tutor | STC Torneos</title>
    @vite(['resources/css/app.css', 'resources/js/ficha.js'])
</head>
<body class="ficha-setup-body">
    <main class="ficha-setup">
        <header class="ficha-setup-hero">
            <img class="ficha-setup-logo" src="{{ asset('images/stc-logo.png') }}" alt="STC Torneos">
            <p class="stc-eyebrow">Ficha del tutor</p>
            <h1>{{ $player->fullName() }}</h1>
            <p class="ficha-setup-meta">
                {{ $player->team?->name }}
                @if ($player->team?->category?->name)
                    · {{ $player->team->category->name }}
                @endif
                @if ($player->team?->tournament?->name)
                    · {{ $player->team->tournament->name }}
                @endif
            </p>
            <p class="ficha-setup-status">
                Enlace: <strong>{{ $invitation->familyStatusLabel() }}</strong>
            </p>
            @unless ($locked)
                <p class="ficha-setup-lead">Completá los pasos: datos del jugador, documentación y autorizaciones legales. Al final enviás todo junto.</p>
            @endunless
        </header>

        <section class="ficha-setup-card">
            @if (session('status'))
                <div class="ficha-setup-alert">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="ficha-setup-alert is-error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @include('ficha.partials.form-body')
        </section>
    </main>
</body>
</html>
