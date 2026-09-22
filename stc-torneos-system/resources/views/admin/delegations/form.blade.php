<x-layouts.stc
    :title="$title.' | STC Torneos'"
    active="Delegaciones"
    :heading="$title"
    subheading="Datos institucionales, delegado principal y equipos vinculados."
>
    <a class="back-link" href="{{ route('admin.delegations.index') }}">← Volver al listado</a>

    @if ($errors->any())
        <div class="system-alert">Revisá los campos marcados antes de guardar.</div>
    @endif

    @php
        $contacts = collect(old('additional_contacts', $delegation->additional_contacts ?? []))
            ->pad(2, ['name' => '', 'role' => '', 'email' => '', 'phone' => ''])
            ->take(2)
            ->values();
    @endphp

    <form class="ficha-review-panel admin-figma-form" action="{{ $url }}" method="post" enctype="multipart/form-data">
        @csrf
        @if ($method === 'PUT')
            @method('PUT')
        @endif

        <h3>Datos institucionales</h3>
        <div class="ficha-row">
            <label class="span-2">Nombre de la delegación / club <input name="name" value="{{ old('name', $delegation->name) }}" placeholder="Ej: San Lorenzo"></label>
            <label>País <input name="country" value="{{ old('country', $delegation->country) }}" placeholder="Ej: Argentina"></label>
        </div>
        <div class="ficha-row">
            <label class="span-2">Ciudad / sede <input name="city" value="{{ old('city', $delegation->city) }}" placeholder="Ej: Buenos Aires"></label>
            <label>Torneo
                <select name="tournament_id">
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}" @selected((int) old('tournament_id', $delegation->tournament_id) === $tournament->id)>{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="ficha-row">
            <div class="span-2">
                <input type="hidden" name="logo_path" value="{{ old('logo_path', $delegation->logo_path) }}">
                <x-ws-shield-field
                    name="logo_file"
                    :src="$delegation->exists ? $delegation->logoUrl() : null"
                    heading="Escudo del club"
                    hint="Este escudo se usa en todos los equipos de la delegación."
                />
            </div>
            <label>Estado
                <select name="status">
                    @foreach (\App\Models\Delegation::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $delegation->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <h3 id="delegado">Delegado principal</h3>
        <div class="ficha-row">
            <label>Nombre del delegado <input name="delegate_name" value="{{ old('delegate_name', $delegation->delegate_name) }}" placeholder="Ej: Martín Sosa"></label>
            <label>Email <input type="email" name="delegate_email" value="{{ old('delegate_email', $delegation->delegate_email) }}" placeholder="delegado@club.com"></label>
            <label>Teléfono <input name="delegate_phone" value="{{ old('delegate_phone', $delegation->delegate_phone) }}" placeholder="+54 11 ..."></label>
        </div>
        <p class="ficha-flow">Al guardar se enviará invitación al delegado para activar su acceso en la App.</p>

        <h3>Equipos vinculados</h3>
        <div class="linked-team-chips">
            @forelse ($delegation->exists ? $delegation->teams : [] as $team)
                <a href="{{ route('admin.teams.edit', $team) }}">{{ $team->category?->birth_year ?? $team->name }}</a>
            @empty
                <span>Sin equipos vinculados todavía.</span>
            @endforelse
            @if ($delegation->exists)
                <a class="add-chip" href="{{ route('admin.teams.create', ['delegation' => $delegation->id]) }}">+ Agregar equipo</a>
            @endif
        </div>

        <h3>Notas internas (solo admin)</h3>
        <div class="ficha-row">
            <label class="span-4"><textarea name="notes" rows="4" placeholder="Observaciones administrativas, historial de revisión...">{{ old('notes', $delegation->notes) }}</textarea></label>
        </div>

        <h3>Responsables adicionales</h3>
        @foreach ($contacts as $index => $contact)
            <div class="ficha-row">
                <label>Nombre <input name="additional_contacts[{{ $index }}][name]" value="{{ $contact['name'] ?? '' }}" placeholder="Opcional"></label>
                <label>Cargo <input name="additional_contacts[{{ $index }}][role]" value="{{ $contact['role'] ?? '' }}" placeholder="Ayudante, médico..."></label>
                <label>Email <input type="email" name="additional_contacts[{{ $index }}][email]" value="{{ $contact['email'] ?? '' }}"></label>
                <label>Teléfono <input name="additional_contacts[{{ $index }}][phone]" value="{{ $contact['phone'] ?? '' }}"></label>
            </div>
        @endforeach

        <div class="admin-figma-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.delegations.index') }}">CANCELAR</a>
            <button class="ficha-save" type="submit">GUARDAR DELEGACIÓN</button>
        </div>
    </form>
</x-layouts.stc>
