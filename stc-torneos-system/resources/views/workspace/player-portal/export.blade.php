<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ficha {{ $player->fullName() }} | STC Torneos</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #fff; color: #111; padding: 2rem; font-family: system-ui, sans-serif; }
        .player-export { max-width: 820px; margin: 0 auto; }
        .player-export h1 { margin: 0 0 .25rem; font-size: 1.5rem; }
        .player-export h2 { margin: 1.5rem 0 .75rem; font-size: 1rem; text-transform: uppercase; letter-spacing: .06em; }
        .player-export-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem 1.5rem; }
        .player-export-grid div span { display: block; color: #666; font-size: .85rem; }
        .player-export-actions { margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        .player-export-actions a, .player-export-actions button { font-weight: 700; cursor: pointer; }
        @media print { .player-export-actions { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <article class="player-export">
        <p>STC Torneos · Ficha del jugador</p>
        <h1>{{ $player->fullName() }}</h1>
        <p>{{ $player->team?->name }} · {{ $player->team?->category?->name }} · {{ $player->team?->tournament?->name }}</p>

        <h2>Estado</h2>
        <div class="player-export-grid">
            <div><span>Estado ficha</span><strong>{{ $player->fileStatusLabel() }}</strong></div>
            <div><span>Revisión</span><strong>{{ $player->reviewStatusLabel() }}</strong></div>
            <div><span>Documentación</span><strong>{{ $player->documentationSummary() }}</strong></div>
            <div><span>Autorizaciones</span><strong>{{ $player->authorizationSummary() }}</strong></div>
            <div><span>Habilitación</span><strong>{{ $player->eligibilityLabel() }}</strong></div>
        </div>

        <h2>Datos personales</h2>
        <div class="player-export-grid">
            <div><span>Documento</span><strong>{{ $player->formattedDocument() }}</strong></div>
            <div><span>Fecha de nacimiento</span><strong>{{ $player->birth_date?->format('d/m/Y') ?? '—' }}</strong></div>
            <div><span>Camiseta</span><strong>{{ $player->jersey_number ? 'N° '.$player->jersey_number : '—' }}</strong></div>
            <div><span>Posición</span><strong>{{ $player->position ?: '—' }}</strong></div>
            <div><span>Domicilio</span><strong>{{ $player->address ?: '—' }}</strong></div>
            <div><span>Talle</span><strong>{{ $player->kit_size ?: '—' }}</strong></div>
        </div>

        <h2>Datos médicos</h2>
        <div class="player-export-grid">
            <div><span>Altura / Peso</span><strong>{{ trim(($player->height ?: '—').' / '.($player->weight ?: '—')) }}</strong></div>
            <div><span>Grupo sanguíneo</span><strong>{{ $player->blood_type ?: '—' }}</strong></div>
            <div><span>Cobertura médica</span><strong>{{ $player->medical_coverage ?: '—' }}</strong></div>
            <div><span>Calendario vacunas</span><strong>{{ $player->vaccinationCalendarLabel() }}</strong></div>
            <div><span>Tratamiento en curso</span><strong>{{ $player->ongoingTreatmentLabel() }}</strong></div>
            <div><span>Contacto emergencia</span><strong>{{ $player->emergency_contact ?: '—' }}</strong></div>
        </div>
        @if ($player->allergies || $player->medication || $player->illnesses || $player->restrictions || $player->medical_notes || $player->ongoing_treatment_notes)
            <p><strong>Observaciones:</strong>
                {{ collect([$player->allergies, $player->medication, $player->illnesses, $player->restrictions, $player->ongoing_treatment_notes, $player->medical_notes])->filter()->implode(' · ') ?: '—' }}
            </p>
        @endif

        @if ($player->guardian)
            <h2>Tutor / responsable</h2>
            <div class="player-export-grid">
                <div><span>Nombre</span><strong>{{ $player->guardian->name }}</strong></div>
                <div><span>Vínculo</span><strong>{{ $player->guardian->relationship ?: '—' }}</strong></div>
                <div><span>Correo</span><strong>{{ $player->guardian->email ?: '—' }}</strong></div>
                <div><span>Teléfono</span><strong>{{ $player->guardian->phone ?: '—' }}</strong></div>
            </div>
        @endif

        <h2>Documentos</h2>
        <ul>
            @foreach (\App\Models\Player::managedDocumentTypes() as $type)
                @php $document = $player->documentByType($type); @endphp
                <li>{{ $type }}: {{ $document?->fileUrl() ? 'Cargado' : 'Sin archivo' }} @if($document?->statusLabel()) ({{ $document->statusLabel() }}) @endif</li>
            @endforeach
        </ul>

        <div class="player-export-actions">
            <button type="button" onclick="window.print()">Imprimir / guardar PDF</button>
            <a href="{{ route('workspace.player.home') }}">Volver a Mi ficha</a>
        </div>
    </article>
</body>
</html>
