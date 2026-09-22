<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña | STC Torneos</title>
    @vite(['resources/css/app.css'])
</head>
<body class="stc-login">
    <main class="figma-login-wrap">
        <section class="figma-form-card">
            <h2>Recuperar contraseña</h2>
            <p class="login-copy">Te enviaremos un enlace a tu correo si está registrado en STC Torneos.</p>

            @if (session('status'))
                <div class="login-alert" style="border-color:rgba(46,255,148,.35);color:#d4ffe8;">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="login-alert">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('password.email') }}" method="post" class="login-form">
                @csrf
                <label>
                    Correo electrónico
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>
                <button type="submit">Enviar enlace</button>
            </form>
            <p class="login-copy"><a href="{{ route('login') }}">← Volver al login</a></p>
        </section>
    </main>
</body>
</html>
