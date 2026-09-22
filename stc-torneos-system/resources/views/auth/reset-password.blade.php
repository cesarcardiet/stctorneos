<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva contraseña | STC Torneos</title>
    @vite(['resources/css/app.css'])
</head>
<body class="stc-login">
    <main class="figma-login-wrap">
        <section class="figma-form-card">
            <h2>Nueva contraseña</h2>
            <p class="login-copy">Definí una contraseña nueva para tu acceso a STC Torneos.</p>

            @if ($errors->any())
                <div class="login-alert">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('password.update') }}" method="post" class="login-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label>
                    Correo electrónico
                    <input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>
                </label>
                <label>
                    Nueva contraseña
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>
                <label>
                    Confirmar contraseña
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
                <button type="submit">Guardar contraseña</button>
            </form>
        </section>
    </main>
</body>
</html>
