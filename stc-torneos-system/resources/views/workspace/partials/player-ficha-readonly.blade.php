@php
    use App\Models\Guardian;
    use App\Models\Player;

    $statuses = Player::statusLabels();
@endphp

<article class="ws-card ws-ficha-readonly">
    <p class="stc-eyebrow">Datos personales</p>
    <div class="ws-player-public-layout">
        <div class="ws-player-photo">
            <img src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
            @if ($player->hasStoredPhoto())
                <a href="{{ $player->photoUrl() }}" target="_blank" rel="noopener">Ver foto</a>
            @endif
        </div>
        <div class="ws-setup-grid">
            <div>
                <span class="ws-muted">Nombre</span>
                <strong>{{ $player->first_name }}</strong>
            </div>
            <div>
                <span class="ws-muted">Apellido</span>
                <strong>{{ $player->last_name }}</strong>
            </div>
            <div>
                <span class="ws-muted">Documento</span>
                <strong>{{ $player->document_number ?: '—' }}</strong>
            </div>
            <div>
                <span class="ws-muted">Nacimiento</span>
                <strong>{{ $player->birth_date?->format('d/m/Y') ?: '—' }}</strong>
            </div>
            <div>
                <span class="ws-muted">Edad</span>
                <strong>{{ $player->age() ? $player->age().' años' : 'Sin cargar' }}</strong>
            </div>
            <div>
                <span class="ws-muted">Nacionalidad</span>
                <strong>{{ $player->nationality ?: '—' }}</strong>
            </div>
            <div class="ws-span-2">
                <span class="ws-muted">Domicilio</span>
                <strong>{{ $player->address ?: '—' }}</strong>
            </div>
        </div>
    </div>

    <p class="stc-eyebrow">Información deportiva</p>
    <div class="ws-setup-grid">
        <div>
            <span class="ws-muted">Equipo</span>
            <strong>{{ $player->team?->name ?: 'Sin equipo' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Delegación</span>
            <strong>{{ $player->team?->delegation?->name ?? $player->team?->delegation_name ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Categoría</span>
            <strong>{{ $category->name }}</strong>
        </div>
        <div>
            <span class="ws-muted">Posición</span>
            <strong>{{ $player->position ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Camiseta</span>
            <strong>{{ $player->jersey_number ? 'N° '.$player->jersey_number : '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Talle</span>
            <strong>{{ $player->kit_size ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Pierna hábil</span>
            <strong>{{ $player->preferred_foot ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Altura</span>
            <strong>{{ $player->height ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Peso</span>
            <strong>{{ $player->weight ?: '—' }}</strong>
        </div>
    </div>

    <p class="stc-eyebrow">Tutor</p>
    <div class="ws-setup-grid">
        <div>
            <span class="ws-muted">Nombre del tutor</span>
            <strong>{{ $player->guardian?->name ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">D.N.I. del tutor</span>
            <strong>{{ $player->guardian?->document_number ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Vínculo</span>
            <strong>{{ $player->guardian?->relationship ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Teléfono</span>
            <strong>{{ $player->guardian?->phone ?: '—' }}</strong>
            @if ($player->guardian?->phone)
                <x-whatsapp-link :url="$player->whatsappShareUrl()" />
            @endif
        </div>
        <div>
            <span class="ws-muted">Email</span>
            <strong>{{ $player->guardian?->email ?: '—' }}</strong>
        </div>
        <div class="ws-span-2">
            <span class="ws-muted">Contacto alternativo</span>
            <strong>{{ $player->guardian?->alternate_contact ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Consentimiento</span>
            <strong>{{ Guardian::consentLabels()[$player->guardian?->consent_status ?? 'pending'] ?? '—' }}</strong>
        </div>
    </div>

    <p class="stc-eyebrow">Información médica</p>
    <div class="ws-setup-grid">
        <div>
            <span class="ws-muted">Grupo sanguíneo</span>
            <strong>{{ $player->blood_type ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Cobertura</span>
            <strong>{{ $player->medical_coverage ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Alergias</span>
            <strong>{{ $player->allergies ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Medicación</span>
            <strong>{{ $player->medication ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Enfermedades</span>
            <strong>{{ $player->illnesses ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Restricciones alimentarias</span>
            <strong>{{ $player->restrictions ?: '—' }}</strong>
        </div>
        <div>
            <span class="ws-muted">Calendario nacional de vacunación</span>
            <strong>{{ $player->vaccinationCalendarLabel() }}</strong>
        </div>
        <div>
            <span class="ws-muted">¿Tratamiento en curso?</span>
            <strong>{{ $player->ongoingTreatmentLabel() }}</strong>
        </div>
        @if ($player->ongoing_treatment)
            <div class="ws-span-2">
                <span class="ws-muted">Detalle del tratamiento</span>
                <strong>{{ $player->ongoing_treatment_notes ?: '—' }}</strong>
            </div>
        @endif
        <div class="ws-span-2">
            <span class="ws-muted">Contacto de emergencia</span>
            <strong>{{ $player->emergency_contact ?: '—' }}</strong>
        </div>
        <div class="ws-span-2">
            <span class="ws-muted">Notas médicas</span>
            <strong>{{ $player->medical_notes ?: '—' }}</strong>
        </div>
    </div>

    <p class="stc-eyebrow">Estado de ficha</p>
    <div class="ws-setup-grid">
        <div>
            <span class="ws-muted">Estado</span>
            <strong>{{ $statuses[$player->status] ?? $player->statusLabel() }}</strong>
        </div>
        <div>
            <span class="ws-muted">Habilitación</span>
            <strong>{{ $player->eligibilityLabel() }}</strong>
        </div>
        @if ($player->observation_reason)
            <div class="ws-span-2">
                <span class="ws-muted">Motivo de observación</span>
                <strong>{{ $player->observation_reason }}</strong>
            </div>
        @endif
    </div>
</article>
