<x-layouts.stc
    :title="'Detalle Usuario y Permisos | STC Torneos'"
    active="Usuarios"
    heading="Detalle Usuario y Permisos"
    :subheading="$user->name.' · '.$user->roleLabel().' · '.$user->tournamentLabel().' · '.$user->statusLabel().'.'"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.users.index') }}">← Volver al listado</a>

    <section class="users-detail-layout">
        <article class="users-profile-card">
            <header>
                <img src="{{ $user->photoUrl() }}" alt="{{ $user->name }}">
                <div>
                    <h2>{{ $user->name }}</h2>
                    <p>{{ $user->roleLabel() }} · {{ $user->tournament?->name ?? $user->tournamentLabel() }}</p>
                </div>
            </header>

            <div class="users-mini-kpis">
                <article class="admin-figma-kpi tone-cyan">
                    <strong>{{ $user->assignedTournamentsCount() }}</strong>
                    <span>Torneos</span>
                </article>
                <article class="admin-figma-kpi tone-green">
                    <strong>{{ $user->permissionSlugs()->count() }}</strong>
                    <span>Permisos</span>
                </article>
            </div>

            <div class="fields-form-grid users-profile-fields">
                <div class="fields-form-field">
                    <span>Correo electrónico</span>
                    <strong>{{ $user->email }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>Teléfono</span>
                    <strong>{{ $user->phone ?: '—' }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>Estado de acceso</span>
                    <strong>{{ $user->statusLabel() }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>Rol principal</span>
                    <strong>{{ $user->roleLabel() }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>Torneo principal</span>
                    <strong>{{ $user->tournament?->name ?? $user->tournamentLabel() }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>Torneo adicional</span>
                    <strong>{{ $user->extraTournament?->name ?: 'Ninguno' }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>Delegación / club</span>
                    <strong>{{ $user->delegation?->name ?: 'Sin club' }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>Roles activos</span>
                    <strong>{{ $user->roles->pluck('name')->join(', ') ?: $user->roleLabel() }}</strong>
                </div>
                @if ($user->suspension_reason)
                    <div class="fields-form-field span-2">
                        <span>Motivo de suspensión</span>
                        <strong>{{ $user->suspension_reason }}</strong>
                    </div>
                @endif
            </div>

            @if ($user->id !== auth()->id())
                <div class="users-danger-actions">
                    @if ($user->status === 'pending')
                        <form method="post" action="{{ route('admin.users.status', $user) }}" data-confirm="¿Aprobar el acceso de {{ $user->name }}?">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="active">
                            <button class="users-danger-btn" type="submit">Aprobar acceso</button>
                        </form>
                    @elseif (in_array($user->status, ['suspended', 'revoked'], true))
                        <form method="post" action="{{ route('admin.users.status', $user) }}" data-confirm="¿Reactivar el acceso de {{ $user->name }}?">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="active">
                            <button class="users-danger-btn" type="submit">Reactivar acceso</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('admin.users.status', $user) }}" data-confirm="¿Suspender el acceso de {{ $user->name }}?">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="suspended">
                            <input type="hidden" name="suspension_reason" value="{{ $user->suspension_reason ?: 'Suspensión operativa' }}">
                            <button class="users-danger-btn" type="submit">Suspender acceso</button>
                        </form>
                    @endif
                    @if ($user->status !== 'revoked')
                        <form method="post" action="{{ route('admin.users.status', $user) }}" data-confirm="¿Revocar el acceso de {{ $user->name }}? No va a poder ingresar al Admin Web.">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="revoked">
                            <button class="users-danger-btn" type="submit">Revocar acceso</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('admin.users.destroy', $user) }}" data-confirm="¿Eliminar a {{ $user->name }}? Esta acción no se puede deshacer.">
                        @csrf
                        @method('DELETE')
                        <button class="users-danger-btn" type="submit">Eliminar usuario</button>
                    </form>
                </div>
            @else
                <p class="sheets-locked-note">Estás viendo tu propio usuario. Podés editar el perfil, pero el estado de acceso lo cambia otro Super Admin.</p>
            @endif

            <a class="users-edit-link" href="{{ route('admin.users.edit', $user) }}">Editar usuario</a>
            <a class="users-edit-link" href="{{ route('admin.users.credential', $user) }}" target="_blank" rel="noopener">Credencial QR</a>
        </article>

        <aside class="users-permission-card" data-permission-list>
            @include('admin.users.partials.permissions', ['capabilities' => $capabilities])
        </aside>
    </section>
</x-layouts.stc>
