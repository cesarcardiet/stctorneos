@props([
    'title' => 'Operación STC',
    'heading' => null,
    'subheading' => null,
    'category' => null,
    'tournament' => null,
    'active' => null,
])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#020714">
    <title>{{ $title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="stc-body ws-body">
    <div class="stc-nav-overlay" data-close-menu></div>
    <div class="stc-shell">
        @include('workspace.partials.sidebar', [
            'category' => $category,
            'tournament' => $tournament,
            'active' => $active,
        ])

        <main class="stc-main ws-main">
            <header class="stc-topbar">
                <button type="button" class="stc-icon-btn stc-menu-btn" data-open-menu aria-label="Abrir menú">☰</button>
                <div class="stc-topbar-copy">
                    <h1>{{ $heading ?? $title }}</h1>
                    <span>{{ $subheading ?? 'Operá el torneo desde la categoría.' }}</span>
                </div>
                @if (isset($actions) && ! $actions->isEmpty())
                    <div class="stc-actions">{{ $actions }}</div>
                @endif
            </header>

            @include('workspace.partials.breadcrumb', [
                'category' => $category,
                'tournament' => $tournament,
                'active' => $active,
            ])

            @if (session('status'))
                <p class="ws-flash">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <p class="ws-flash">{{ $errors->first() }}</p>
            @endif

            {{ $slot }}
        </main>
    </div>
    @include('workspace.partials.shield-crop-modal')
    @stack('scripts')
</body>
</html>
