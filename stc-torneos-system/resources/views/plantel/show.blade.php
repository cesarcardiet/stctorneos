<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plantel · {{ $team->name }} | STC Torneos</title>
    @vite(['resources/css/app.css'])
</head>
<body class="stc-login">
    <main class="figma-login-wrap ficha-tutor-wrap">
        <section class="figma-info-card">
            <img class="login-logo-img" src="{{ asset('images/stc-logo.png') }}" alt="STC Torneos">
            <p class="stc-eyebrow">Enlace de plantel</p>
            <h1>{{ $team->name }}</h1>
            <p class="login-copy">{{ $category?->name }} · {{ $tournament?->name }}</p>
            <p class="login-copy">Estado del enlace: <b>{{ $invitation->familyStatusLabel() }}</b></p>
            @if ($invitation->expires_at)
                <p class="login-copy">Vence: {{ $invitation->expires_at->format('d/m/Y H:i') }}</p>
            @endif
            @unless ($locked)
                <p class="login-copy">Cargá jugadores con nombre, apellido, DNI y datos del tutor. No hace falta crear una cuenta.</p>
            @endunless
        </section>

        <section class="figma-form-card">
            <h2>Plantel ({{ $team->players->count() }})</h2>

            @if (session('status'))
                <div class="login-alert">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="login-alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if ($locked)
                <p class="login-copy">{{ $lockReason ?? 'Las inscripciones están cerradas.' }}</p>
            @else
                <form class="login-form plantel-add-form" method="post" action="{{ route('plantel.store', $invitation->token) }}">
                    @csrf
                    <p class="stc-eyebrow">Nuevo jugador</p>
                    <label>Apellido
                        <input type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="120" autocomplete="family-name">
                    </label>
                    <label>Nombre
                        <input type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="120" autocomplete="given-name">
                    </label>
                    <label>DNI (opcional)
                        <input type="text" name="document_number" value="{{ old('document_number') }}" maxlength="40" inputmode="numeric">
                    </label>
                    <p class="stc-eyebrow">Tutor / responsable</p>
                    <label>Nombre del tutor
                        <input type="text" name="guardian_name" value="{{ old('guardian_name') }}" maxlength="160" autocomplete="name">
                    </label>
                    <label>Teléfono
                        <input type="tel" name="guardian_phone" value="{{ old('guardian_phone') }}" maxlength="80" autocomplete="tel">
                    </label>
                    <label>Email
                        <input type="email" name="guardian_email" value="{{ old('guardian_email') }}" maxlength="180" autocomplete="email">
                    </label>
                    <p class="login-copy">Si cargás tutor, indicá teléfono o email.</p>
                    <button type="submit">Añadir jugador</button>
                </form>
            @endif

            @if ($team->players->isNotEmpty())
                <p class="stc-eyebrow plantel-loaded-title">Jugadores cargados</p>
                <ul class="plantel-loaded-list">
                    @foreach ($team->players as $player)
                        <li>
                            <strong>{{ strtoupper($player->last_name) }} {{ $player->first_name }}</strong>
                            @if ($player->document_number)
                                <span>· DNI {{ $player->document_number }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>
</body>
</html>
