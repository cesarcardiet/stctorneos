<x-layouts.stc
    title="Crear / Editar Usuario | STC Torneos"
    active="Usuarios"
    heading="Crear / Editar Usuario"
    subheading="Alta y habilitación de usuarios administrativos, roles y torneos asignados."
>
    <a class="back-link" href="{{ route('admin.users.index') }}">← Volver al listado</a>

    @if ($errors->any())
        <div class="system-alert">Revisá los datos del usuario antes de guardar.</div>
    @endif

    <section class="users-detail-layout">
        <form class="users-form-card admin-figma-form" method="post" action="{{ $url }}" id="user-form">
            @csrf
            @if ($method === 'PUT')
                @method('PUT')
            @endif
            <input type="hidden" name="current_scope" value="{{ old('current_scope', $user->current_scope) }}">

            <h3>Datos del usuario</h3>
            <div class="fields-form-grid">
                <label class="fields-form-field">
                    <span>Nombre completo</span>
                    <input name="name" value="{{ old('name', $user->name) }}" placeholder="Ej: Sebastián Martínez" required>
                </label>
                <label class="fields-form-field">
                    <span>Correo electrónico</span>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" placeholder="sebastian@stc.com" required>
                </label>
                <label class="fields-form-field">
                    <span>Teléfono</span>
                    <input name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+54 11 5555 0101">
                </label>
                <label class="fields-form-field">
                    <span>Rol principal</span>
                    <select name="role_id" id="user-role">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((int) old('role_id', $user->primaryRole()?->id) === (int) $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <h3>Asignación de torneos</h3>
            <div class="fields-form-grid">
                <label class="fields-form-field">
                    <span>Torneo principal</span>
                    <select name="tournament_id">
                        <option value="">Sistema / todos</option>
                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}" @selected((int) old('tournament_id', $user->tournament_id) === (int) $tournament->id)>{{ $tournament->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fields-form-field">
                    <span>Torneos adicionales</span>
                    <select name="extra_tournament_id">
                        <option value="">Ninguno</option>
                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}" @selected((int) old('extra_tournament_id', $user->extra_tournament_id) === (int) $tournament->id)>{{ $tournament->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fields-form-field">
                    <span>Delegación / club</span>
                    <select name="delegation_id">
                        <option value="">Sin club</option>
                        @foreach ($delegations as $delegation)
                            <option value="{{ $delegation->id }}" @selected((int) old('delegation_id', $user->delegation_id) === $delegation->id)>{{ $delegation->name }} · {{ $delegation->tournament?->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fields-form-field">
                    <span>Estado de acceso</span>
                    <select name="status" id="user-status">
                        @foreach (\App\Models\User::statusLabels() as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $user->status ?: 'pending') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fields-form-field span-2">
                    <span>Motivo de suspensión</span>
                    <input name="suspension_reason" value="{{ old('suspension_reason', $user->suspension_reason) }}" placeholder="N/A">
                </label>
            </div>
            <p class="users-form-note">Al guardar se enviará invitación por correo para activar el acceso al Admin Web.</p>

            <h3>Roles activos del usuario</h3>
            <div class="users-role-chips">
                @php
                    $selectedRoleIds = collect(old('role_ids', $user->exists ? $user->roles->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
                @endphp
                @foreach ($roles as $role)
                    <label class="users-role-chip">
                        <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($selectedRoleIds->contains((int) $role->id) || (int) old('role_id', $user->primaryRole()?->id) === (int) $role->id)>
                        <span>{{ $role->name }}</span>
                    </label>
                @endforeach
            </div>

            <div class="admin-figma-form-actions users-form-actions">
                <a class="admin-figma-cancel" href="{{ route('admin.users.index') }}">CANCELAR</a>
                <button class="ficha-save" type="submit">Guardar usuario</button>
            </div>
        </form>

        <aside class="users-permission-card" data-permission-list>
            @include('admin.users.partials.permissions', ['capabilities' => $capabilities])
        </aside>
    </section>

    <script>
        const catalog = @json(\App\Models\User::capabilityCatalog());
        const rolePerms = @json($rolePermissionMap);
        const list = document.querySelector('[data-permission-list]');

        const selectedSlugs = () => {
            const ids = [...document.querySelectorAll('[name="role_ids[]"]:checked')].map((input) => input.value);
            const primary = document.querySelector('[name="role_id"]')?.value;
            if (primary && !ids.includes(primary)) ids.push(primary);
            return [...new Set(ids.flatMap((id) => rolePerms[id] || []))];
        };

        const stateFor = (item, slugs) => {
            const hasAll = item.slugs.every((slug) => slugs.includes(slug));
            const hasSome = item.slugs.some((slug) => slugs.includes(slug));
            const fullAdmin = slugs.includes('users.manage') && slugs.includes('roles.manage');
            if (hasAll && item.limited && !fullAdmin) return ['limited', 'Limitado'];
            if (hasAll) return ['active', 'Activo'];
            if (hasSome) return ['limited', 'Limitado'];
            return ['denied', 'No permitido'];
        };

        const render = () => {
            if (!list) return;
            const slugs = selectedSlugs();
            list.innerHTML = catalog.map((item) => {
                const [state, label] = stateFor(item, slugs);
                return `<article class="users-permission-row is-${state}"><div><strong>${item.label}</strong><small>${label}</small></div><b>${label}</b></article>`;
            }).join('');
        };

        document.querySelector('[name="role_id"]')?.addEventListener('change', (event) => {
            const checkbox = document.querySelector(`[name="role_ids[]"][value="${event.target.value}"]`);
            if (checkbox) checkbox.checked = true;
            render();
        });
        document.querySelectorAll('[name="role_ids[]"]').forEach((input) => input.addEventListener('change', render));
        render();
    </script>
</x-layouts.stc>
