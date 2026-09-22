@php
    $navUser = auth()->user();
    $navUser?->loadMissing(['roles', 'tournament']);
    $items = \App\Support\AdminNavigation::items($navUser);
@endphp

<aside class="stc-sidebar" id="stc-sidebar">
    <div class="stc-sidebar-head">
        <a href="{{ route('workspace.home') }}" class="stc-brand" aria-label="STC Operación">
            <img class="stc-brand-logo" src="{{ asset('images/stc-logo.png') }}" alt="STC Torneos">
            <small>Operación</small>
        </a>
        <button type="button" class="stc-icon-btn stc-sidebar-close" data-close-menu aria-label="Cerrar menú">✕</button>
    </div>

    @if ($navUser)
        <div class="stc-user-card">
            <strong>{{ $navUser->name }}</strong>
            <span>{{ $navUser->roleLabel() }}</span>
            <small>{{ $navUser->sidebarScope() }}</small>
        </div>
    @endif

    <nav class="stc-nav">
        @foreach ($items as [$label, $href, $meta])
            <a href="{{ $href }}" @class(['active' => ($active ?? 'Dashboard') === $label])>
                <span>
                    <strong>{{ $label }}</strong>
                    <small>{{ $meta }}</small>
                </span>
                <b>›</b>
            </a>
        @endforeach
    </nav>
    <a class="stc-logout" href="{{ route('logout.demo') }}">Salir</a>
</aside>
