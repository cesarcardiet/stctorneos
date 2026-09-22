<x-layouts.stc
    :title="$title.' | STC Torneos'"
    active="Jugadores"
    :heading="$title"
    :subheading="$player->exists ? $player->fullName().' · '.($player->team?->name ?? 'Sin equipo').' '.($player->team?->category?->birth_year ?? '').' · '.$player->statusLabel() : 'Alta de ficha para lista de buena fe.'"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <section class="ficha-review-head">
        <div>
            <a class="back-link" href="{{ route('admin.players.index') }}">← Volver a Lista de Buena Fe</a>
            @if ($player->exists)
                <div class="ficha-review-actions">
                    <form method="post" action="{{ route('admin.players.status', $player) }}">
                        @csrf
                        @method('PATCH')
                        <button class="ficha-pill approve" type="submit" name="status" value="approved">Aprobar</button>
                    </form>
                    <form method="post" action="{{ route('admin.players.status', $player) }}">
                        @csrf
                        @method('PATCH')
                        <button class="ficha-pill observe" type="submit" name="status" value="observed">Observar</button>
                    </form>
                    <form method="post" action="{{ route('admin.players.status', $player) }}">
                        @csrf
                        @method('PATCH')
                        <button class="ficha-pill reject" type="submit" name="status" value="rejected">Rechazar</button>
                    </form>
                    <form method="post" action="{{ route('admin.players.status', $player) }}">
                        @csrf
                        @method('PATCH')
                        <button class="ficha-pill enable" type="submit" name="status" value="enabled">Habilitar para jugar</button>
                    </form>
                    <form method="post" action="{{ route('admin.players.status', $player) }}">
                        @csrf
                        @method('PATCH')
                        <button class="ficha-pill reject" type="submit" name="status" value="blocked">Bloquear</button>
                    </form>
                </div>
            @endif
        </div>
        <article class="ficha-count-card">
            <strong>{{ number_format($playerCount, 0, ',', '.') }}</strong>
            <span>Jugadores</span>
        </article>
    </section>

    @if ($errors->any())
        <div class="system-alert">Revisá los campos marcados antes de guardar.</div>
    @endif

    <form id="player-form" class="ficha-review-panel admin-figma-form" action="{{ $url }}" method="post" enctype="multipart/form-data">
        @csrf
        @if ($method === 'PUT')
            @method('PUT')
        @endif

        <h3>Información personal</h3>
        <div class="ficha-row">
            <label>Nombre <input name="first_name" value="{{ old('first_name', $player->first_name) }}" placeholder="Thiago"></label>
            <label>Apellido <input name="last_name" value="{{ old('last_name', $player->last_name) }}" placeholder="Martínez"></label>
            <label>Documento <input name="document_number" value="{{ old('document_number', $player->document_number) }}" placeholder="45.678.901"></label>
            <label>Fecha nac. <input type="date" name="birth_date" data-birth-date value="{{ old('birth_date', optional($player->birth_date)->format('Y-m-d')) }}" min="1990-01-01" max="{{ now()->format('Y-m-d') }}"></label>
        </div>
        <div class="ficha-row">
            <label>Nacionalidad
                <x-country-select
                    name="nationality"
                    :value="old('nationality', $player->nationality ?: 'Argentina')"
                    required
                />
            </label>
            <label class="span-2">Domicilio <input name="address" value="{{ old('address', $player->address) }}" placeholder="Av. La Plata 1234, CABA"></label>
            <label>Fotografía
                <input type="hidden" name="photo_path" value="{{ old('photo_path', $player->photo_path) }}">
                <input type="file" name="photo_file" accept="image/png,image/jpeg,image/webp">
                <img class="ficha-photo-preview" src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
                @if ($player->hasStoredPhoto())
                    <small><a href="{{ $player->photoUrl() }}" target="_blank" rel="noopener">Ver foto</a></small>
                @else
                    <small>Sin cargar</small>
                @endif
            </label>
        </div>

        <h3>Información deportiva</h3>
        <div class="ficha-row">
            <label>Equipo
                <select name="team_id">
                    <option value="">Elegí un equipo</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected((int) old('team_id', $player->team_id) === $team->id)>
                            {{ $team->name }} · {{ $team->category?->name ?? $team->category?->birth_year }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>Categoría <input value="{{ $player->team?->category?->birth_year ?? $player->team?->category?->name }}" placeholder="2014" disabled></label>
            <label>Posición
                <select name="position">
                    @foreach (['Arquero', 'Defensor', 'Mediocampista', 'Delantero'] as $position)
                        <option value="{{ $position }}" @selected(old('position', $player->position) === $position)>{{ $position }}</option>
                    @endforeach
                </select>
            </label>
            <label>Número <input type="number" name="jersey_number" min="1" max="99" value="{{ old('jersey_number', $player->jersey_number) }}" placeholder="10"></label>
            <label class="span-2">Talle
                <x-ws-kit-size :value="$player->kit_size" />
            </label>
        </div>
        <div class="ficha-row">
            <label>Pierna hábil
                <select name="preferred_foot">
                    @foreach (['Derecha', 'Izquierda', 'Ambidiestro'] as $foot)
                        <option value="{{ $foot }}" @selected(old('preferred_foot', $player->preferred_foot) === $foot)>{{ $foot }}</option>
                    @endforeach
                </select>
            </label>
            <label>Altura <input name="height" value="{{ old('height', $player->height) }}" placeholder="1.62 m"></label>
            <label>Peso <input name="weight" value="{{ old('weight', $player->weight) }}" placeholder="52 kg"></label>
            <label>Delegación <input value="{{ $player->team?->delegation?->name ?? $player->team?->delegation_name }}" placeholder="San Lorenzo" disabled></label>
        </div>

        <h3>Información familiar</h3>
        <div class="ficha-row">
            <label>Responsable <input name="guardian_name" value="{{ old('guardian_name', $guardian->name) }}" placeholder="María Martínez"></label>
            <label>D.N.I. del tutor <input name="guardian_document_number" value="{{ old('guardian_document_number', $guardian->document_number) }}" placeholder="30123456"></label>
            <label>Vínculo
                <select name="guardian_relationship">
                    @foreach (['Madre', 'Padre', 'Tutor legal', 'Familiar', 'Delegado'] as $relationship)
                        <option value="{{ $relationship }}" @selected(old('guardian_relationship', $guardian->relationship) === $relationship)>{{ $relationship }}</option>
                    @endforeach
                </select>
            </label>
            <label>Teléfono
                <input name="guardian_phone" value="{{ old('guardian_phone', $guardian->phone) }}" placeholder="+54 11 5555-1234">
                @if ($player->whatsappShareUrl())
                    <a class="wa-share" href="{{ $player->whatsappShareUrl() }}" target="_blank" rel="noopener">WhatsApp</a>
                @else
                    <x-whatsapp-link :phone="old('guardian_phone', $guardian->phone)" />
                @endif
            </label>
            <label>Email <input type="email" name="guardian_email" value="{{ old('guardian_email', $guardian->email) }}" placeholder="maria@email.com"></label>
        </div>
        <div class="ficha-row">
            <label class="span-4">Contacto alternativo <input name="guardian_alternate_contact" value="{{ old('guardian_alternate_contact', $guardian->alternate_contact) }}" placeholder="Carlos Martínez (padre) · +54 11 5555-5678"></label>
        </div>

        <h3>Información médica</h3>
        <div class="ficha-row">
            <label>Grupo sanguíneo <input name="blood_type" value="{{ old('blood_type', $player->blood_type) }}" placeholder="O+"></label>
            <label>Cobertura <input name="medical_coverage" value="{{ old('medical_coverage', $player->medical_coverage) }}" placeholder="OSDE 210"></label>
            <label>Alergias <input name="allergies" value="{{ old('allergies', $player->allergies) }}" placeholder="Ninguna"></label>
            <label>Medicación <input name="medication" value="{{ old('medication', $player->medication) }}" placeholder="Ninguna"></label>
        </div>
        <div class="ficha-row">
            <label>Enfermedades <input name="illnesses" value="{{ old('illnesses', $player->illnesses) }}" placeholder="Ninguna"></label>
            <label>Restricciones alimentarias <input name="restrictions" value="{{ old('restrictions', $player->restrictions) }}" placeholder="Ej. celíaco, sin lactosa o Ninguna"></label>
            <label>Calendario nacional de vacunación
                @php
                    $vaccinationValue = old(
                        'vaccination_calendar_complete',
                        $player->vaccination_calendar_complete === null ? '' : ($player->vaccination_calendar_complete ? '1' : '0')
                    );
                @endphp
                <select name="vaccination_calendar_complete">
                    <option value="" @selected($vaccinationValue === '')>Sin cargar</option>
                    <option value="1" @selected($vaccinationValue === '1')>Sí</option>
                    <option value="0" @selected($vaccinationValue === '0')>No</option>
                </select>
            </label>
            <label class="span-2">Contacto emergencia <input name="emergency_contact" value="{{ old('emergency_contact', $player->emergency_contact) }}" placeholder="María Martínez · +54 11 5555-1234"></label>
        </div>
        <div class="ficha-row">
            <x-ws-medical-yes-no
                name="ongoing_treatment"
                detail-name="ongoing_treatment_notes"
                label="¿Tratamiento en curso?"
                detail-label="Detalle del tratamiento"
                :value="$player->ongoing_treatment"
                :detail="$player->ongoing_treatment_notes"
                detail-class="span-4"
            />
        </div>
        <div class="ficha-row">
            <label class="span-4">Notas médicas <textarea name="medical_notes" rows="3" placeholder="Observaciones clínicas relevantes">{{ old('medical_notes', $player->medical_notes) }}</textarea></label>
        </div>

        <h3>Documentación y autorizaciones</h3>
        <div class="ficha-doc-chips">
            @foreach (\App\Models\Player::documentTypes() as $type)
                @php $approved = in_array($type, old('documents', $player->documents->where('status', 'approved')->pluck('type')->all()), true) || ($player->exists === false && in_array($type, old('documents', []), true)); @endphp
                <label class="ficha-doc-chip">
                    <input type="checkbox" name="documents[]" value="{{ $type }}" @checked($player->hasApprovedDocument($type) || in_array($type, old('documents', []), true))>
                    <span class="mark"></span>
                    {{ $type }}
                </label>
            @endforeach
        </div>

        <h3>Estado de ficha</h3>
        <p class="ficha-flow">Flujo: Registro inicial → Pendiente de Tutor → En proceso → Enviado → En revisión → Observado / Aprobado → Habilitado</p>
        <div class="ficha-row">
            <label>Estado actual
                <select name="status">
                    @foreach (\App\Models\Player::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $player->status ?: 'pending') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Habilitación <input value="{{ $player->eligibilityLabel() }}" disabled></label>
            <label class="span-2">Motivo observación <input name="observation_reason" value="{{ old('observation_reason', $player->observation_reason) }}" placeholder="(vacío)"></label>
        </div>

        <div class="admin-figma-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.players.index') }}">CANCELAR</a>
            <button class="ficha-save" type="submit">Guardar cambios</button>
        </div>
    </form>
</x-layouts.stc>
