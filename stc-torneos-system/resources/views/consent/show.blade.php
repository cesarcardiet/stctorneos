<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autorización parental | STC Torneos</title>
    @vite(['resources/css/app.css'])
</head>
<body class="stc-login">
    <main class="figma-login-wrap">
        <section class="figma-form-card">
            <h2>Autorización de ficha</h2>
            <p class="login-copy">
                Jugador: <strong>{{ $player->fullName() }}</strong><br>
                @if ($player->team?->name) Equipo: <strong>{{ $player->team->name }}</strong><br> @endif
                @if ($player->team?->category?->name) Categoría: <strong>{{ $player->team->category->name }}</strong> @endif
            </p>

            @if (session('status'))
                <div class="login-alert" style="border-color:rgba(46,255,148,.35);color:#d4ffe8;">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="login-alert">{{ $errors->first() }}</div>
            @endif

            <form action="{{ request()->fullUrl() }}" method="post" class="login-form">
                @csrf
                <p class="login-copy">Como tutor/a responsable, confirmá si autorizás la participación y el tratamiento de los datos de la ficha.</p>
                <button type="submit" name="consent_status" value="approved">Autorizo</button>
                <button type="submit" name="consent_status" value="rejected" style="margin-top:.5rem;background:#4a2030;">No autorizo</button>
            </form>
        </section>
    </main>
</body>
</html>
