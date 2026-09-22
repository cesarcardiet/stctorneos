<x-layouts.stc
    title="Roles y permisos | STC Torneos"
    active="Usuarios"
    heading="Roles y permisos"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <p class="player-filter-bar">
        <a class="stc-button ghost" href="{{ route('admin.users.index') }}">← Volver a usuarios</a>
    </p>

    <section class="sheets-list-card admin-figma-panel">
        <h3>Roles del sistema</h3>
        <div class="delegation-figma-table sheets-figma-table admin-figma-table">
            <div class="table-head">
                <span>Rol</span>
                <span>Alcance</span>
                <span>Usuarios</span>
                <span>Permisos</span>
                <span>Acción</span>
            </div>
            @foreach ($roles as $role)
                <div class="table-row">
                    <span><strong>{{ $role->name }}</strong><br><small>{{ $role->slug }}</small></span>
                    <span>{{ $role->scope ?: '—' }}</span>
                    <span>{{ $role->users_count }}</span>
                    <span>{{ $role->permissions_count }}</span>
                    <span class="category-actions">
                        @if ($role->slug === 'super-admin')
                            <span class="ws-muted">Protegido</span>
                        @else
                            <a href="{{ route('admin.roles.edit', $role) }}">Editar permisos</a>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.stc>
