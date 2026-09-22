@php
    $steps = [
        1 => 'Confirmar',
        2 => 'Personal',
        3 => 'Deporte',
        4 => 'Médica',
        5 => 'Archivos',
        6 => 'Responsab.',
        7 => 'Imagen',
        8 => 'Aptitud',
        9 => 'Enviar',
    ];

    $altRaw = trim((string) old('guardian_alternate_contact', $guardian->alternate_contact));
    $altName = old('guardian_alternate_name');
    $altPhone = old('guardian_alternate_phone');
    if ($altName === null || $altPhone === null) {
        if (str_contains($altRaw, '·')) {
            [$altNameParsed, $altPhoneParsed] = array_pad(array_map('trim', explode('·', $altRaw, 2)), 2, '');
            $altName = $altName ?? $altNameParsed;
            $altPhone = $altPhone ?? $altPhoneParsed;
        } elseif (preg_match('/^(.*?)(\+?\d[\d\s\-]{6,})$/u', $altRaw, $m)) {
            $altName = $altName ?? trim($m[1]);
            $altPhone = $altPhone ?? trim($m[2]);
        } else {
            $altName = $altName ?? $altRaw;
            $altPhone = $altPhone ?? '';
        }
    }

    $guardianParts = preg_split('/\s+/', trim((string) old('guardian_name', $guardian->name)), 2) ?: ['', ''];
    $guardianFirst = old('guardian_first_name', $guardianParts[0] ?? '');
    $guardianLast = old('guardian_last_name', $guardianParts[1] ?? '');

    $none = static fn (?string $value): string => filled(trim((string) $value)) ? trim((string) $value) : 'Ninguna';
@endphp

<div class="ficha-wizard-head">
    <div class="ficha-wizard-progress" aria-hidden="true">
        <span data-ficha-progress-bar style="width: 11%"></span>
    </div>
    <p class="stc-eyebrow" data-step-label>Paso 1 de 9</p>
    <div class="ficha-wizard-dots ficha-wizard-dots-9" aria-label="Progreso de la ficha">
        @foreach ($steps as $step => $label)
            <button type="button" class="ficha-step-dot @if($step === 1) is-active @endif" data-step-dot="{{ $step }}" title="{{ $label }}">
                <span>{{ $step }}</span>
                <small>{{ $label }}</small>
            </button>
        @endforeach
    </div>
</div>

<div class="login-alert ficha-toast" data-ficha-alert hidden role="alert" aria-live="assertive">
    <div class="ficha-toast-body">
        <strong data-ficha-alert-title>Revisá este dato</strong>
        <p data-ficha-alert-text></p>
    </div>
    <button type="button" class="ficha-toast-close" data-ficha-alert-close aria-label="Cerrar">✕</button>
</div>

<form method="post" action="{{ $fichaSubmitUrl ?? route('ficha.update', $invitation->token) }}" class="login-form ficha-wizard-form" data-ficha-form enctype="multipart/form-data">
    @csrf

    <section class="ficha-wizard-panel" data-step="1">
        <p class="stc-eyebrow">Paso 1 · Confirmar datos del delegado</p>
        <p class="login-copy">Revisá los datos que cargó el delegado del club. Si algo está mal, corregilo antes de continuar.</p>

        <p class="stc-eyebrow">Jugador/a</p>
        <label>Apellido
            <input name="last_name" value="{{ old('last_name', $player->last_name) }}" required autocomplete="family-name" data-ficha-text>
        </label>
        <label>Nombre
            <input name="first_name" value="{{ old('first_name', $player->first_name) }}" required autocomplete="given-name" data-ficha-text>
        </label>
        <label>D.N.I. del jugador/a
            <input name="document_number" value="{{ old('document_number', $player->document_number) }}" required inputmode="numeric" pattern="[0-9]{7,8}" maxlength="8" placeholder="Ej. 50123456" data-ficha-dni autocomplete="off">
            <small class="ficha-field-hint">Solo números, 7 u 8 dígitos.</small>
        </label>

        <p class="stc-eyebrow">Tutor / responsable</p>
        <label>Nombre del tutor
            <input name="guardian_first_name" value="{{ $guardianFirst }}" required autocomplete="given-name" data-ficha-guardian-part data-ficha-text>
        </label>
        <label>Apellido del tutor
            <input name="guardian_last_name" value="{{ $guardianLast }}" required autocomplete="family-name" data-ficha-guardian-part data-ficha-text>
        </label>
        <input type="hidden" name="guardian_name" value="{{ trim($guardianFirst.' '.$guardianLast) }}" data-ficha-guardian-full>
        <label>Teléfono
            <input name="phone" value="{{ old('phone', $guardian->phone) }}" autocomplete="tel" inputmode="tel">
        </label>
        <label>Email del tutor
            <input type="email" name="email" value="{{ old('email', $guardian->email ?: $invitation->email) }}" required autocomplete="email" data-ficha-email="tutor">
            <small class="ficha-field-hint" data-email-hint="tutor">Se verifica mientras escribís.</small>
        </label>
        @error('email')
            <p class="login-alert" style="margin-top: .5rem;">{{ $message }}</p>
        @enderror
        <p class="login-copy ws-muted">En el <strong>paso 9 · Enviar</strong> podés cargar el correo del jugador/a para habilitar su acceso al portal.</p>

        <p class="stc-eyebrow">Completá tus datos como tutor</p>
        <label>D.N.I. del tutor
            <input name="guardian_document_number" value="{{ old('guardian_document_number', $guardian->document_number) }}" required inputmode="numeric" pattern="[0-9]{7,8}" maxlength="8" autocomplete="off" placeholder="Ej. 30123456" data-ficha-dni>
            <small class="ficha-field-hint">Solo números, 7 u 8 dígitos.</small>
        </label>
        <label>Vínculo
            <select name="relationship" required>
                <option value="">Elegí vínculo</option>
                @foreach (\App\Models\Guardian::relationshipOptions() as $relationship)
                    <option value="{{ $relationship }}" @selected(old('relationship', $guardian->relationship) === $relationship)>{{ $relationship }}</option>
                @endforeach
            </select>
        </label>
        <label>Contacto alternativo · nombre
            <input name="guardian_alternate_name" value="{{ $altName }}" placeholder="Nombre y apellido" required>
        </label>
        <label>Contacto alternativo · teléfono
            <input name="guardian_alternate_phone" value="{{ $altPhone }}" placeholder="+54 9 11 ..." inputmode="tel" required>
        </label>

        <div class="ficha-player-card">
            <p class="stc-eyebrow">Contexto</p>
            <p class="login-copy"><b>Equipo:</b> {{ $authContext['team_name'] }}</p>
            <p class="login-copy"><b>Categoría:</b> {{ $player->team?->category?->name }}</p>
            <p class="login-copy"><b>Delegación:</b> {{ $player->team?->delegation?->name ?? $player->team?->delegation_name }}</p>
            <p class="login-copy"><b>Torneo:</b> {{ $authContext['tournament_name'] }}</p>
        </div>
    </section>

    <section class="ficha-wizard-panel" data-step="2" hidden>
        <p class="stc-eyebrow">Paso 2 · Datos personales del jugador</p>
        <p class="login-copy">Completá nacimiento, nacionalidad y domicilio. Las fotos del DNI van en el paso 5.</p>

        <label>Fecha de nacimiento
            <input
                type="text"
                name="birth_date_display"
                value="{{ old('birth_date_display', $player->birth_date?->format('d/m/Y')) }}"
                inputmode="numeric"
                autocomplete="bday"
                maxlength="10"
                placeholder="DD/MM/AAAA"
                pattern="\d{2}/\d{2}/\d{4}"
                data-ficha-birth-display
                required
            >
            <input type="hidden" name="birth_date" value="{{ old('birth_date', $player->birth_date?->format('Y-m-d')) }}" data-ficha-birth-value>
            <small class="ficha-field-hint">Día 01–31 · Mes 01–12 · Año desde 1990 (ej. 12/06/2014).</small>
        </label>
        <label>Nacionalidad
            <x-country-select name="nationality" :value="$player->nationality ?: 'Argentina'" />
        </label>
        <label>Domicilio
            <input name="address" value="{{ old('address', $player->address) }}" placeholder="Calle, número, ciudad" required autocomplete="street-address">
        </label>
    </section>

    <section class="ficha-wizard-panel" data-step="3" hidden>
        <p class="stc-eyebrow">Paso 3 · Información deportiva</p>
        <p class="login-copy">Indicá talle, posición y datos físicos del/de la jugador/a.</p>

        <label>Talle de camiseta
            <select name="kit_size" required>
                <option value="">Elegí talle</option>
                @foreach (\App\Models\Player::kitSizeGroups() as $group => $sizes)
                    <optgroup label="{{ $group }}">
                        @foreach ($sizes as $size)
                            <option value="{{ $size }}" @selected((string) old('kit_size', $player->kit_size) === (string) $size)>{{ $size }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </label>
        <label>N° de camiseta
            <input type="number" name="jersey_number" min="1" max="99" step="1" inputmode="numeric" value="{{ old('jersey_number', $player->jersey_number) }}" placeholder="Ej. 10" data-ficha-int>
        </label>
        <label>Posición
            <select name="position">
                <option value="">Elegí posición</option>
                @foreach (\App\Models\Player::positions() as $position)
                    <option value="{{ $position }}" @selected(old('position', $player->position) === $position)>{{ $position }}</option>
                @endforeach
            </select>
        </label>
        <label>Pierna hábil
            <select name="preferred_foot">
                <option value="">Elegí pierna</option>
                @foreach (\App\Models\Player::preferredFeet() as $foot)
                    <option value="{{ $foot }}" @selected(old('preferred_foot', $player->preferred_foot) === $foot)>{{ $foot }}</option>
                @endforeach
            </select>
        </label>
        <label>Altura (mts)
            <input name="height" value="{{ old('height', $player->height) }}" placeholder="Ej. 1.62" inputmode="decimal" required data-ficha-decimal data-ficha-min="0.5" data-ficha-max="2.5">
            <small class="ficha-field-hint">Solo número, en metros (ej. 1.55).</small>
        </label>
        <label>Peso (Kg.)
            <input name="weight" value="{{ old('weight', $player->weight) }}" placeholder="Ej. 52" inputmode="decimal" required data-ficha-decimal data-ficha-min="15" data-ficha-max="150">
            <small class="ficha-field-hint">Solo número, en kilogramos (ej. 48).</small>
        </label>
    </section>

    <section class="ficha-wizard-panel" data-step="4" hidden>
        <p class="stc-eyebrow">Paso 4 · Información médica</p>
        <p class="login-copy">Todos los campos son obligatorios. Si no aplica, dejá <b>Ninguna</b>.</p>

        <label>Grupo sanguíneo
            <select name="blood_type" required>
                <option value="">Elegí grupo</option>
                @foreach (\App\Models\Player::bloodTypes() as $blood)
                    <option value="{{ $blood }}" @selected(old('blood_type', $player->blood_type) === $blood)>{{ $blood }}</option>
                @endforeach
            </select>
        </label>
        <label>Cobertura médica
            <input name="medical_coverage" value="{{ old('medical_coverage', $none($player->medical_coverage)) }}" placeholder="OSDE / Swiss Medical / Ninguna" required data-ficha-none-default>
        </label>
        <label>Alergias
            <input name="allergies" value="{{ old('allergies', $none($player->allergies)) }}" placeholder="Ninguna" required data-ficha-none-default>
        </label>
        <label>Medicación
            <input name="medication" value="{{ old('medication', $none($player->medication)) }}" placeholder="Ninguna" required data-ficha-none-default>
        </label>
        <label>Enfermedades
            <input name="illnesses" value="{{ old('illnesses', $none($player->illnesses)) }}" placeholder="Ninguna" required data-ficha-none-default>
        </label>
        <label>Restricciones alimentarias
            <input name="restrictions" value="{{ old('restrictions', $none($player->restrictions)) }}" placeholder="Ninguna" required data-ficha-none-default>
        </label>
        <label>Calendario nacional de vacunación
            <select name="vaccination_calendar_complete" required>
                <option value="" @selected(old('vaccination_calendar_complete', $player->vaccination_calendar_complete === null ? '' : ($player->vaccination_calendar_complete ? '1' : '0')) === '')>Elegí</option>
                <option value="1" @selected(old('vaccination_calendar_complete', $player->vaccination_calendar_complete === null ? '' : ($player->vaccination_calendar_complete ? '1' : '0')) === '1')>Sí</option>
                <option value="0" @selected(old('vaccination_calendar_complete', $player->vaccination_calendar_complete === null ? '' : ($player->vaccination_calendar_complete ? '1' : '0')) === '0')>No</option>
            </select>
        </label>
        <x-ws-medical-yes-no
            name="ongoing_treatment"
            detail-name="ongoing_treatment_notes"
            label="¿Tratamiento en curso?"
            detail-label="Detalle del tratamiento"
            :value="$player->ongoing_treatment"
            :detail="$player->ongoing_treatment_notes"
        />
        <label>Observaciones
            <textarea name="emergency_contact" rows="3" placeholder="Observaciones médicas o de cuidado" required data-ficha-none-default>{{ old('emergency_contact', $none($player->emergency_contact)) }}</textarea>
        </label>
    </section>

    <section class="ficha-wizard-panel" data-step="5" hidden>
        <p class="stc-eyebrow">Paso 5 · Documentación</p>
        <p class="login-copy">DNI frente, DNI dorso y foto del jugador son obligatorios. Sacá la foto con el celular: el sistema la deja lista sola.</p>

        @php
            $photoDoc = $player->documentByType('Foto del jugador');
            $photoUrl = $photoDoc?->fileUrl() ?: $player->photoUrl();
            $photoUploaded = filled($photoDoc?->file_path) || filled($player->photo_path);
        @endphp

        <div class="ficha-photo-field" data-ficha-photo data-doc-uploaded="{{ $photoUploaded ? '1' : '0' }}">
            <p class="stc-eyebrow">Foto del jugador</p>
            <div class="ficha-photo-preview-wrap">
                <img src="{{ $photoUrl }}" alt="Foto del jugador" data-ficha-photo-preview>
            </div>
            <div class="ficha-photo-actions">
                <label class="ficha-wizard-secondary" style="display:inline-flex;align-items:center;cursor:pointer;">
                    Elegir foto
                    <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-ficha-photo-file hidden>
                </label>
                <button type="button" class="ficha-wizard-secondary" data-ficha-photo-adjust>Ajustar</button>
            </div>
            <input type="hidden" name="photo_data" value="" data-ficha-photo-data>
            <input type="file" name="document_files[Foto del jugador]" accept="image/png,image/jpeg,image/webp,image/gif" data-doc-input="Foto del jugador" hidden>
        </div>

        <ul class="ficha-doc-list">
            @foreach (['DNI frente', 'DNI dorso', 'Cobertura médica'] as $type)
                @php $document = $player->documentByType($type); @endphp
                @include('ficha.partials.document-upload', [
                    'type' => $type,
                    'document' => $document,
                    'required' => in_array($type, \App\Models\Player::tutorRequiredDocumentTypes(), true),
                ])
            @endforeach
        </ul>
    </section>

    <section class="ficha-wizard-panel" data-step="6" hidden>
        <p class="stc-eyebrow">Paso 6 · Autorización y responsabilidad</p>
        <div class="ficha-legal-text" data-legal="participation">{{ \App\Support\GuardianAuthorizationTexts::participation($authContext) }}</div>
        <label class="ficha-auth-check">
            <input type="checkbox" name="auth[]" value="Autorización" data-auth="Autorización" @checked($authAccepted['Autorización'] ?? false)>
            Leí y acepto la autorización y aceptación de responsabilidad.
        </label>
    </section>

    <section class="ficha-wizard-panel" data-step="7" hidden>
        <p class="stc-eyebrow">Paso 7 · Uso de imagen</p>
        <div class="ficha-legal-text" data-legal="image">{{ \App\Support\GuardianAuthorizationTexts::imageUse($authContext) }}</div>
        <label class="ficha-auth-check">
            <input type="checkbox" name="auth[]" value="Uso de imagen" data-auth="Uso de imagen" @checked($authAccepted['Uso de imagen'] ?? false)>
            Autorizo el uso de fotografías y videos según el texto anterior.
        </label>
    </section>

    <section class="ficha-wizard-panel" data-step="8" hidden>
        <p class="stc-eyebrow">Paso 8 · Aptitud médica</p>
        <div class="ficha-legal-text" data-legal="medical">{{ \App\Support\GuardianAuthorizationTexts::medicalFitness($authContext) }}</div>
        <label class="ficha-auth-check">
            <input type="checkbox" name="auth[]" value="Apto médico" data-auth="Apto médico" @checked($authAccepted['Apto médico'] ?? false)>
            Declaro la aptitud médica y me responsabilizo por la declaración presentada.
        </label>
    </section>

    <section class="ficha-wizard-panel" data-step="9" hidden>
        <p class="stc-eyebrow">Paso 9 · Revisión y envío</p>
        <div class="ficha-player-card" data-ficha-summary>
            <p class="login-copy"><b>Tutor:</b> <span data-summary-guardian>{{ $authContext['guardian_name'] ?: '________________' }}</span> · DNI <span data-summary-guardian-document>{{ $authContext['guardian_document'] ?: '________________' }}</span></p>
            <p class="login-copy"><b>Jugador/a:</b> <span data-summary-player>{{ $authContext['player_name'] }}</span> · DNI <span data-summary-player-document>{{ $authContext['player_document'] ?: '________________' }}</span></p>
            <p class="login-copy"><b>Nacimiento:</b> <span data-summary-birth>{{ $player->birth_date?->format('d/m/Y') ?: '________________' }}</span> · <b>Talle:</b> <span data-summary-kit>{{ $player->kit_size ?: '________________' }}</span></p>
            <p class="login-copy"><b>Altura:</b> <span data-summary-height>{{ $player->height ?: '________________' }}</span> mts · <b>Peso:</b> <span data-summary-weight>{{ $player->weight ?: '________________' }}</span> Kg.</p>
            <p class="login-copy"><b>Equipo:</b> <span data-summary-team>{{ $authContext['team_name'] }}</span></p>
            <p class="login-copy"><b>Torneo:</b> <span data-summary-tournament>{{ $authContext['tournament_name'] }}</span></p>
        </div>

        <div class="ficha-player-access-card">
            <p class="stc-eyebrow">Acceso al portal del jugador</p>
            <p class="login-copy">Opcional. Si cargás el correo del jugador/a, al enviar la ficha podrá ingresar al sistema para ver su credencial, documentos y el torneo.</p>
            <label>Correo electrónico del jugador/a
                <input type="email" name="player_email" value="{{ old('player_email', $player->email) }}" autocomplete="email" placeholder="correo.del.jugador@email.com" data-ficha-email="player">
                <small class="ficha-field-hint" data-email-hint="player">Opcional. Se verifica mientras escribís.</small>
            </label>
            @error('player_email')
                <p class="login-alert" style="margin-top: .5rem;">{{ $message }}</p>
            @enderror
            <p class="login-copy ws-muted">Clave inicial: <strong>{{ \App\Support\PlayerCredentials::defaultPassword() }}</strong> · cambiable en Mi cuenta después del primer ingreso.</p>
        </div>

        <p class="login-copy">Al enviar, los datos, archivos y autorizaciones quedarán registrados en la ficha para revisión del administrador.</p>
        <label class="ficha-auth-check">
            <input type="checkbox" name="consent" value="1" @checked(old('consent'))>
            Confirmo que los datos son correctos y autorizo la participación de {{ $player->fullName() }}.
        </label>
    </section>

    <div class="ficha-wizard-actions">
        <button type="button" class="ficha-wizard-secondary" data-prev hidden>Anterior</button>
        <button type="button" class="ficha-wizard-primary" data-next>Siguiente</button>
        <button type="submit" name="complete" value="1" class="ficha-wizard-primary" data-complete hidden>Enviar ficha del tutor</button>
    </div>
</form>

<div class="ficha-crop-modal" data-ficha-crop-modal hidden>
    <div class="ficha-crop-card">
        <p class="stc-eyebrow">Ajustar foto del jugador</p>
        <p class="login-copy">Arrastrá la imagen y usá el zoom para recortar.</p>
        <div class="ficha-crop-frame">
            <div class="ficha-crop-stage" data-ficha-crop-stage>
                <img data-ficha-crop-image alt="Recorte" draggable="false">
            </div>
        </div>
        <label class="login-copy">Zoom
            <input type="range" min="1" max="3" step="0.01" value="1" data-ficha-crop-zoom>
        </label>
        <div class="ficha-wizard-actions">
            <button type="button" class="ficha-wizard-secondary" data-ficha-crop-cancel>Cancelar</button>
            <button type="button" class="ficha-wizard-primary" data-ficha-crop-apply>Usar recorte</button>
        </div>
    </div>
</div>
