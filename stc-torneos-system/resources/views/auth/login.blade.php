<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#020714">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Acceso Admin | STC Torneos</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stc-login">
    <main class="figma-login-wrap">
        <section class="figma-info-card">
            <img class="login-logo-img" src="{{ asset('images/stc-logo.png') }}" alt="STC Torneos">
            <p class="stc-eyebrow">STC Torneos</p>
            <span>Sistema de gestión deportiva</span>
            <small>Gestión de torneo</small>
            <h1>Entrá a una categoría y operá equipos, partidos, tablas y configuración desde un solo lugar.</h1>
            <p class="login-copy">Pensado para usar en mesa, simple y directo. Misma línea visual STC.</p>
            <div class="role-pills">
                <strong>Admin</strong>
                <strong>Delegado</strong>
                <strong>Árbitro</strong>
            </div>
        </section>

        <section class="figma-form-card">
            <h2>Iniciar sesión</h2>
            <p class="login-copy">Elegí un perfil demo. La clave de todos es <b>stcdemo</b>.</p>

            @if (session('status'))
                <div class="login-alert" style="border-color:rgba(46,255,148,.35);color:#d4ffe8;">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="login-alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.perform') }}" method="post" class="login-form">
                @csrf
                <label>
                    Perfil demo
                    <select id="login-profile">
                        @foreach (\App\Support\AdminNavigation::demoAccounts() as [$role, $name, $email, $sees])
                            <option value="{{ $email }}" @selected($email === old('email', 'admin@stctorneos.demo'))>
                                {{ $role }} · {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Correo electrónico
                    <input id="login-email" type="email" name="email" value="{{ old('email', 'admin@stctorneos.demo') }}" autocomplete="email">
                </label>
                <label>
                    Contraseña
                    <input type="password" name="password" value="stcdemo" autocomplete="current-password">
                </label>
                <div class="login-options">
                    <label><input type="checkbox" name="remember" value="1"> Recordarme</label>
                    <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                </div>
                <button type="submit">Ingresar</button>
            </form>
            <small class="login-demo-note">Demo funcional. Clave: stcdemo. Mesa (Carla) queda pendiente y no entra.</small>
        </section>
    </main>
    <script>
        const profile = document.getElementById('login-profile');
        const email = document.getElementById('login-email');
        const syncEmail = () => {
            if (profile && email) {
                email.value = profile.value;
            }
        };
        profile?.addEventListener('change', syncEmail);
    </script>
</body>
</html>
