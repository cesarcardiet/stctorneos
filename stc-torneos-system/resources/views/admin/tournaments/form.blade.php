<x-layouts.stc
    :title="$title.' | STC Torneos'"
    active="Torneos"
    :heading="$title"
    subheading="Configuración completa del torneo, sedes, fechas y visibilidad pública."
>
    <a class="back-link" href="{{ route('admin.tournaments.index') }}">← Volver al listado</a>

    @if ($errors->any())
        <div class="system-alert">Revisá los campos marcados antes de guardar.</div>
    @endif

    <form class="ficha-review-panel admin-figma-form" action="{{ $url }}" method="post" enctype="multipart/form-data">
        @csrf
        @if ($method === 'PUT')
            @method('PUT')
        @endif

        <h3>Identificación</h3>
        <div class="ficha-row">
            <label>Nombre del torneo <input name="name" value="{{ old('name', $tournament->name) }}" placeholder="Santa Teresita Cup 2026"></label>
            <label>Edición <input name="edition" value="{{ old('edition', $tournament->edition) }}" placeholder="Edición 2026"></label>
            <div class="span-2" data-ws-tourney-brand>
                <x-ws-shield-field
                    :src="$tournament->exists ? $tournament->logoUrl() : asset('images/stc-logo.png')"
                    heading="Imagen del torneo"
                    hint="Se ve en el listado, en el encabezado y en el dashboard."
                />
            </div>
        </div>

        <h3>Ubicación</h3>
        <div class="ficha-row">
            <label>País <input name="country" value="{{ old('country', $tournament->country ?? 'Argentina') }}" placeholder="Argentina"></label>
            <label>Ciudad <input name="city" value="{{ old('city', $tournament->city) }}" placeholder="Santa Teresita"></label>
            <label>Sede <input name="venue_name" value="{{ old('venue_name', $tournament->venue_name) }}" placeholder="Complejo Deportivo Santa Teresita"></label>
            <label>Zona horaria <input name="timezone" value="{{ old('timezone', $tournament->timezone ?? 'America/Argentina/Buenos_Aires') }}"></label>
        </div>

        <h3>Fechas e inscripción</h3>
        <div class="ficha-row">
            <label>Fecha inicio <input type="date" name="starts_at" value="{{ old('starts_at', $tournament->starts_at?->format('Y-m-d')) }}" min="1900-01-01" max="2100-12-31"></label>
            <label>Fecha cierre <input type="date" name="ends_at" value="{{ old('ends_at', $tournament->ends_at?->format('Y-m-d')) }}" min="1900-01-01" max="2100-12-31"></label>
            <label>Inicio inscripción <input type="date" name="registration_starts_at" value="{{ old('registration_starts_at', $tournament->registration_starts_at?->format('Y-m-d')) }}" min="1900-01-01" max="2100-12-31"></label>
            <label>Cierre inscripción <input type="date" name="registration_ends_at" value="{{ old('registration_ends_at', $tournament->registration_ends_at?->format('Y-m-d')) }}" min="1900-01-01" max="2100-12-31"></label>
        </div>

        <h3>Estado y visibilidad</h3>
        <div class="ficha-row">
            <label>Visibilidad
                <select name="visibility">
                    @foreach (['private' => 'Privado', 'public' => 'Público'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('visibility', $tournament->visibility ?? 'private') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Estado operativo
                <select name="status">
                    @foreach (['draft' => 'Borrador', 'registration' => 'Inscripción', 'preparation' => 'Preparación', 'in_progress' => 'En curso', 'finished' => 'Finalizado', 'archived' => 'Archivado'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $tournament->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="span-2">Reglamento / URL <input name="rules_url" value="{{ old('rules_url', $tournament->rules_url) }}" placeholder="https://stctorneos.demo/reglamento"></label>
        </div>

        <h3>Información</h3>
        <div class="ficha-row">
            <label class="span-2">Descripción <textarea name="description" rows="4" placeholder="Descripción administrativa y alcance del torneo">{{ old('description', $tournament->description) }}</textarea></label>
            <label class="span-2">Información general <textarea name="general_info" rows="4" placeholder="Información institucional y observaciones">{{ old('general_info', $tournament->general_info) }}</textarea></label>
        </div>

        <h3>Contacto</h3>
        <div class="ficha-row">
            <label>Contacto <input name="contact_name" value="{{ old('contact_name', $tournament->contact_name) }}" placeholder="Mesa Central STC"></label>
            <label>Email <input type="email" name="contact_email" value="{{ old('contact_email', $tournament->contact_email) }}" placeholder="operacion@stctorneos.demo"></label>
            <label class="span-2">Teléfono <input name="contact_phone" value="{{ old('contact_phone', $tournament->contact_phone) }}" placeholder="+54 9 11 5555-2026"></label>
        </div>

        <div class="admin-figma-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.tournaments.index') }}">CANCELAR</a>
            <button class="ficha-save" type="submit">Guardar torneo</button>
        </div>
    </form>
</x-layouts.stc>
