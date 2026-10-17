@auth
<div class="stc-notif-bell" data-notif-bell data-user-id="{{ auth()->id() }}">
    <button type="button" class="stc-notif-bell__btn" data-notif-toggle aria-label="Notificaciones" aria-expanded="false">
        <span class="stc-notif-bell__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
        </span>
        <span class="stc-notif-bell__badge" data-notif-badge hidden>0</span>
    </button>
    <div class="stc-notif-bell__panel" data-notif-panel hidden>
        <header class="stc-notif-bell__head">
            <strong>Notificaciones</strong>
            <button type="button" class="stc-notif-bell__mark" data-notif-mark-all>Marcar leídas</button>
        </header>
        <div class="stc-notif-bell__list" data-notif-list>
            <p class="stc-notif-bell__empty">Cargando…</p>
        </div>
        <footer class="stc-notif-bell__foot">
            @if (auth()->user()?->hasPermission('communications.manage'))
                <a href="{{ route('admin.communications.notifications') }}">Administrar avisos</a>
            @else
                <span>Tus avisos del torneo</span>
            @endif
        </footer>
    </div>
</div>
@endauth
