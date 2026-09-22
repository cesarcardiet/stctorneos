<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activar cuenta | STC Torneos</title>
    @vite(['resources/css/app.css'])
</head>
<body class="stc-login">
    <main class="figma-login-wrap">
        <section class="figma-form-card">
            <h2>Activar cuenta</h2>
            <p class="login-copy">Hola <strong>{{ $invitation->name }}</strong>. Definí tu contraseña para acceder a STC Torneos.</p>

            @if ($errors->any())
                <div class="login-alert">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('invitations.accept', $invitation->token) }}" method="post" class="login-form">
                @csrf
                <label>
                    Correo
                    <input type="email" value="{{ $invitation->email }}" disabled>
                </label>
                <label>
                    Contraseña
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>
                <label>
                    Confirmar contraseña
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
                <button type="submit">Activar cuenta</button>
            </form>
        </section>
    </main>
</body>
</html>
