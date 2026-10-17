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
    <title>{{ $title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.pwa-head')
    <script>
        window.StcRealtime = {
            enabled: @json(config('broadcasting.default') === 'reverb'),
            key: @json(config('broadcasting.connections.reverb.key')),
            host: @json(config('broadcasting.connections.reverb.options.host') ?: request()->getHost()),
            port: @json((int) config('broadcasting.connections.reverb.options.port', 443)),
            scheme: @json(config('broadcasting.connections.reverb.options.scheme', 'https')),
            userId: @json(auth()->id()),
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="stc-body ws-body stc-app">
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
                <div class="stc-actions ws-topbar-actions">
                    @include('admin.partials.notification-bell')
                    @if (isset($actions) && ! $actions->isEmpty())
                        {{ $actions }}
                    @endif
                </div>
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
    @include('workspace.partials.app-nav', [
        'category' => $category,
        'tournament' => $tournament,
        'active' => $active,
    ])
    @include('workspace.partials.shield-crop-modal')
    @include('workspace.partials.doc-preview-modal')
    @stack('scripts')
</body>
</html>
