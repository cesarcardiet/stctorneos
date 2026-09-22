<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro por invitación | STC Torneos</title>
    @vite(['resources/css/app.css'])
</head>
<body class="stc-login">
    <main class="figma-login-wrap">
        <section class="figma-form-card">
            <h2>Registro por invitación</h2>
            <p class="login-copy">Ingresá el correo y el código que te envió el administrador para activar tu acceso.</p>

            @if (session('status'))
                <div class="login-alert" style="border-color:rgba(46,255,148,.35);color:#d4ffe8;">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="login-alert">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('register.perform') }}" method="post" class="login-form">
                @csrf
                <label>
                    Nombre completo
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </label>
                <label>
                    Correo electrónico
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>
                <label>
                    Código de invitación
                    <input type="text" name="invitation_code" value="{{ old('invitation_code') }}" required>
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

            <p class="login-copy" style="margin-top:1rem;">
                ¿Ya tenés cuenta? <a href="{{ route('login') }}">Iniciar sesión</a>
            </p>
        </section>
    </main>
</body>
</html>
