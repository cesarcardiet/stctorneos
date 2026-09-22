<x-layouts.stc
    title="Editar rol · {{ $role->name }} | STC Torneos"
    active="Usuarios"
    heading="Editar rol"
    :subheading="$role->name"
>
    <a class="back-link" href="{{ route('admin.roles.index') }}">← Volver a roles</a>

    @if ($errors->any())
        <div class="system-alert">Revisá los datos del rol antes de guardar.</div>
    @endif

    <form class="users-form-card admin-figma-form" method="post" action="{{ route('admin.roles.update', $role) }}">
        @csrf
        @method('PUT')

        <h3>Datos del rol</h3>
        <div class="fields-form-grid">
            <label class="fields-form-field">
                <span>Nombre</span>
                <input type="text" name="name" value="{{ old('name', $role->name) }}" required>
            </label>
            <label class="fields-form-field">
                <span>Color</span>
                <input type="color" name="color" value="{{ old('color', $role->color ?: '#006bff') }}">
            </label>
            <label class="fields-form-field span-2">
                <span>Descripción</span>
                <input type="text" name="description" value="{{ old('description', $role->description) }}" placeholder="Qué puede hacer este rol">
            </label>
        </div>

        <h3>Permisos</h3>
        @foreach ($permissions as $group => $items)
            <p class="stc-eyebrow">{{ $group }}</p>
            <div class="users-permission-grid">
                @foreach ($items as $permission)
                    <label class="ws-check">
                        <input
                            type="checkbox"
                            name="permission_ids[]"
                            value="{{ $permission->id }}"
                            @checked(in_array($permission->id, old('permission_ids', $assigned), true))
                        >
                        <span>
                            {{ $permission->name }}
                            <small>{{ $permission->slug }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
        @endforeach

        <div class="admin-figma-form-actions users-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.roles.index') }}">CANCELAR</a>
            <button type="submit" class="ficha-save">Guardar rol</button>
        </div>
    </form>
</x-layouts.stc>
