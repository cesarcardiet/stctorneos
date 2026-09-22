<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#020714">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>{{ $title ?? 'STC Torneos' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stc-body">
    <div class="stc-nav-overlay" data-close-menu></div>
    <div class="stc-shell">
            @include('admin.partials.sidebar')

        <main class="stc-main">
            <header class="stc-topbar">
                @php
                    $newHref = match ($active ?? null) {
                        'Categorías' => route('admin.categories.create'),
                        'Delegaciones' => route('admin.delegations.create'),
                        'Equipos' => route('admin.teams.create'),
                        'Jugadores' => route('admin.players.create'),
                        'Campos' => route('admin.fields.create'),
                        'Fixture' => route('admin.fixture.create'),
                        'Planillas' => route('admin.sheets.create'),
                        'Resultados' => route('admin.results.index'),
                        'Usuarios' => route('admin.users.create'),
                        'Comunicaciones' => route('admin.communications.create'),
                        default => route('admin.tournaments.create'),
                    };
                @endphp
                <button type="button" class="stc-icon-btn stc-menu-btn" data-open-menu aria-label="Abrir menú" aria-expanded="false" aria-controls="stc-sidebar">☰</button>
                <div class="stc-topbar-copy">
                    <h1>{{ $heading ?? 'Dashboard Admin' }}</h1>
                    <span>{{ $subheading ?? 'Sistema operativo demo basado en el alcance MVP.' }}</span>
                </div>
                <div class="stc-actions">
                    @include('admin.partials.topbar-search')
                    @if (($active ?? '') === 'Jugadores')
                        <a href="{{ route('admin.players.export', request()->query()) }}">Exportar</a>
                    @endif
                    @if (($active ?? '') === 'Auditoría')
                        <a href="{{ route('admin.audit.export', request()->query()) }}">Exportar</a>
                    @endif
                    @if (! in_array($active ?? '', ['Documentación', 'Resultados', 'Auditoría', 'Revisión'], true) && \App\Support\AdminNavigation::canCreate($active ?? null, auth()->user()))
                        <a href="{{ $newHref }}">Nuevo</a>
                    @endif
                </div>
            </header>

            {{ $slot }}
        </main>
    </div>

    <div class="stc-confirm-modal" data-confirm-modal hidden>
        <div class="stc-confirm-card">
            <span class="stc-confirm-icon">!</span>
            <div>
                <h2>Confirmar acción</h2>
                <p data-confirm-message>¿Querés continuar?</p>
            </div>
            <div class="stc-confirm-actions">
                <button type="button" data-confirm-cancel>Cancelar</button>
                <button type="button" data-confirm-accept>Aceptar</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.querySelector('[data-confirm-modal]');
            if (!modal) return;

            const message = modal.querySelector('[data-confirm-message]');
            const accept = modal.querySelector('[data-confirm-accept]');
            const cancel = modal.querySelector('[data-confirm-cancel]');
            let pendingForm = null;

            document.querySelectorAll('form[data-confirm]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    pendingForm = form;
                    message.textContent = form.dataset.confirm || '¿Querés continuar?';
                    modal.hidden = false;
                });
            });

            cancel.addEventListener('click', () => {
                pendingForm = null;
                modal.hidden = true;
            });

            accept.addEventListener('click', () => {
                if (pendingForm) pendingForm.submit();
            });
        });
    </script>
    @include('workspace.partials.shield-crop-modal')
    @include('workspace.partials.doc-preview-modal')
</body>
</html>
