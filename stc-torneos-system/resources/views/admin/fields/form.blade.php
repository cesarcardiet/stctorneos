<x-layouts.stc
    title="Crear / Editar Sede o Cancha | STC Torneos"
    active="Campos"
    heading="Crear / Editar Sede o Cancha"
    subheading="Gestión de sedes, canchas, disponibilidad y ubicación operativa."
>
    <a class="back-link" href="{{ route('admin.fields.index') }}">← Volver al listado</a>

    @if ($errors->any())
        <div class="system-alert">Revisá los datos de la sede y la cancha antes de guardar.</div>
    @endif

    <div class="fields-editor-layout">
    <form class="ficha-review-panel admin-figma-form fields-editor-card" method="post" action="{{ $url }}">
        @csrf
        @if ($method === 'PUT')
            @method('PUT')
        @endif

        <input type="hidden" name="venue_id" value="{{ old('venue_id', $venue->id) }}">
        <input type="hidden" name="map_url" value="{{ old('map_url', $venue->map_url) }}">

        <h3>Sede y cancha</h3>
        <div class="ficha-row">
            <label>Torneo
                <select name="tournament_id">
                    @foreach ($accessibleTournaments as $tournament)
                        <option value="{{ $tournament->id }}" @selected((int) old('tournament_id', $venue->tournament_id) === (int) $tournament->id)>{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Sede
                <input name="venue_name" list="venue-list" value="{{ old('venue_name', $venue->name) }}" placeholder="Polideportivo STC" autocomplete="off">
                <datalist id="venue-list">
                    @foreach ($venues as $option)
                        <option value="{{ $option->name }}"></option>
                    @endforeach
                </datalist>
            </label>
            <label>Cancha <input name="name" value="{{ old('name', $field->name) }}" placeholder="Cancha 3"></label>
            <label>Estado sede
                <select name="venue_status">
                    @foreach (\App\Models\Venue::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('venue_status', $venue->status ?: 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="ficha-row">
            <label>Dirección <input name="address" value="{{ old('address', $venue->address) }}" placeholder="Av. Costanera 1200"></label>
            <label>Ciudad <input name="city" value="{{ old('city', $venue->city) }}" placeholder="Santa Teresita"></label>
            <label>Superficie <input name="surface" value="{{ old('surface', $field->surface) }}" placeholder="Sintético"></label>
            <label>Modalidad <input name="modality" value="{{ old('modality', $field->modality) }}" placeholder="Fútbol 7"></label>
        </div>
        <div class="ficha-row">
            <label>Horario inicio <input type="time" name="opens_at" value="{{ old('opens_at', $field->opens_at ? \Illuminate\Support\Carbon::parse($field->opens_at)->format('H:i') : '08:00') }}"></label>
            <label>Horario cierre <input type="time" name="closes_at" value="{{ old('closes_at', $field->closes_at ? \Illuminate\Support\Carbon::parse($field->closes_at)->format('H:i') : '20:00') }}"></label>
            <label>Estado
                <select name="status">
                    @foreach (\App\Models\Field::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $field->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Iluminación
                <select name="lighting">
                    <option value="1" @selected(old('lighting', $field->lighting))>Sí</option>
                    <option value="0" @selected(! old('lighting', $field->lighting))>No</option>
                </select>
            </label>
            <label class="span-2">Observaciones <input name="notes" value="{{ old('notes', $field->notes) }}" placeholder="Acceso por portón lateral · estacionamiento limitado"></label>
        </div>

        <h3>Franjas por día</h3>
        <div class="fields-slots">
            @foreach ($slots ?? [] as $day => $slot)
                <div class="fields-slot-row">
                    <strong>{{ $slot['label'] }}</strong>
                    <label>
                        <span>Abre</span>
                        <input type="time" name="slots[{{ $day }}][opens_at]" value="{{ old('slots.'.$day.'.opens_at', $slot['opens_at']) }}">
                    </label>
                    <label>
                        <span>Cierra</span>
                        <input type="time" name="slots[{{ $day }}][closes_at]" value="{{ old('slots.'.$day.'.closes_at', $slot['closes_at']) }}">
                    </label>
                    <label class="fields-slot-closed">
                        <input type="checkbox" name="slots[{{ $day }}][closed]" value="1" @checked(old('slots.'.$day.'.closed', $slot['closed']))>
                        <span>Cerrada</span>
                    </label>
                </div>
            @endforeach
        </div>

        <div class="admin-figma-form-actions">
            <button class="ficha-save" type="submit">Guardar sede</button>
            <a class="fields-map-cta" id="fields-map-link" href="{{ $venue->exists ? $venue->mapUrl() : '#' }}" target="_blank" rel="noopener">Ver mapa</a>
        </div>
        <a class="back-link" href="{{ route('admin.fields.index') }}">Cancelar</a>
    </form>

    <aside class="fields-preview-panel">
        @include('admin.fields.partials.map')
        <strong id="fields-preview-name">{{ strtoupper(old('name', $field->name) ?: 'CANCHA 3') }}</strong>
    </aside>
    </div>

    <script>
        const venues = @json($venueOptions);
        const venueName = document.querySelector('[name="venue_name"]');
        const venueId = document.querySelector('[name="venue_id"]');
        const mapInput = document.querySelector('[name="map_url"]');
        const mapLink = document.querySelector('#fields-map-link');
        const previewName = document.querySelector('#fields-preview-name');
        const nameInput = document.querySelector('[name="name"]');

        const fillVenue = () => {
            const typed = (venueName?.value || '').trim().toLowerCase();
            const match = Object.entries(venues).find(([, venue]) => (venue.name || '').toLowerCase() === typed);
            if (!match) {
                if (venueId) venueId.value = '';
                return;
            }
            const [id, venue] = match;
            if (venueId) venueId.value = id;
            const city = document.querySelector('[name="city"]');
            const address = document.querySelector('[name="address"]');
            const status = document.querySelector('[name="venue_status"]');
            if (city) city.value = venue.city || '';
            if (address) address.value = venue.address || '';
            if (status) status.value = venue.status || 'active';
            if (mapInput) mapInput.value = venue.map_url || '';
            const tournament = document.querySelector('[name="tournament_id"]');
            if (tournament && venue.tournament_id) tournament.value = venue.tournament_id;
        };

        const updateMapLink = () => {
            if (!mapLink) return;
            if (mapInput?.value) {
                mapLink.href = mapInput.value;
                return;
            }
            const query = `${document.querySelector('[name="address"]')?.value || ''} ${document.querySelector('[name="city"]')?.value || ''} ${venueName?.value || ''}`.trim();
            mapLink.href = `https://www.openstreetmap.org/search?query=${encodeURIComponent(query)}`;
        };

        venueName?.addEventListener('change', () => { fillVenue(); updateMapLink(); });
        venueName?.addEventListener('blur', () => { fillVenue(); updateMapLink(); });
        document.querySelector('[name="address"]')?.addEventListener('input', updateMapLink);
        document.querySelector('[name="city"]')?.addEventListener('input', updateMapLink);
        nameInput?.addEventListener('input', () => {
            if (previewName) previewName.textContent = (nameInput.value || 'CANCHA 3').toUpperCase();
        });
        updateMapLink();
    </script>
</x-layouts.stc>
