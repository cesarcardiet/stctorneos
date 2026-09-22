<x-layouts.stc
    :title="$title.' | STC Torneos'"
    active="Equipos"
    :heading="$title"
    :subheading="$team->name.' · máximo DT, preparador físico y ayudante de campo'"
>
    <a class="back-link" href="{{ route('admin.teams.show', $team) }}">← Volver al equipo</a>

    @if ($errors->any())
        <div class="system-alert">Revisá los campos marcados antes de guardar.</div>
    @endif

    @if ($roles === [] && ! $member->exists)
        <div class="system-alert">Este equipo ya tiene los tres cargos activos. Retirá uno para agregar otro.</div>
    @else
        <form class="ficha-review-panel" action="{{ $url }}" method="post" enctype="multipart/form-data">
            @csrf
            @if ($method === 'PUT')
                @method('PUT')
            @endif

            <h3>Integrante</h3>
            <div class="ficha-row">
                <label>Nombre <input name="first_name" value="{{ old('first_name', $member->first_name) }}"></label>
                <label>Apellido <input name="last_name" value="{{ old('last_name', $member->last_name) }}"></label>
                <label>Documento <input name="document_number" value="{{ old('document_number', $member->document_number) }}" placeholder="DNI"></label>
                <label>Cargo
                    <select name="role">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $member->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="ficha-row">
                <label>Estado
                    <select name="status">
                        @foreach (\App\Models\TeamStaff::statusLabels() as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $member->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="span-2">Fotografía
                    <input type="hidden" name="photo_path" value="{{ old('photo_path', $member->photo_path) }}">
                    <input type="file" name="photo_file" accept="image/png,image/jpeg,image/webp">
                    <small>{{ $member->photo_path ? 'Cargada ✓' : 'Sin cargar' }}</small>
                </label>
            </div>

            <button class="ficha-save" type="submit">Guardar</button>
        </form>
    @endif

    @if ($member->exists)
        <form method="post" action="{{ route('admin.teams.staff.withdraw', [$team, $member]) }}" data-confirm="¿Retirar a esta persona del cuerpo técnico?">
            @csrf
            @method('PATCH')
            <button class="ghost-hero" type="submit" style="margin-top: 1rem;">Retirar</button>
        </form>
    @endif
</x-layouts.stc>
