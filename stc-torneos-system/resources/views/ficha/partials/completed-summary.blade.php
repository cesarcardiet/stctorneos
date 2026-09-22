<div class="ficha-player-card">
    <p class="login-copy"><b>D.N.I.:</b> {{ $player->document_number ?: 'Sin cargar' }}</p>
    <p class="login-copy"><b>Nacimiento:</b> {{ $player->birth_date?->format('d/m/Y') ?: 'Sin cargar' }}</p>
    <p class="login-copy">
        <b>Talle:</b> {{ $player->kit_size ?: 'Sin cargar' }}
        @if ($player->jersey_number)
            · <b>Camiseta:</b> {{ $player->jersey_number }}
        @endif
        @if ($player->position)
            · <b>Posición:</b> {{ $player->position }}
        @endif
    </p>
    <p class="login-copy"><b>Altura:</b> {{ $player->height ?: 'Sin cargar' }} mts · <b>Peso:</b> {{ $player->weight ?: 'Sin cargar' }} Kg.</p>
    @if ($player->email)
        <p class="login-copy"><b>Email jugador (portal):</b> {{ $player->email }}</p>
    @endif
    @if ($player->illnesses || $player->allergies || $player->restrictions)
        <p class="login-copy"><b>Enfermedades:</b> {{ $player->illnesses ?: 'Ninguna' }} · <b>Alergias:</b> {{ $player->allergies ?: 'Ninguna' }} · <b>Restricciones alimentarias:</b> {{ $player->restrictions ?: 'Ninguna' }}</p>
    @endif
</div>
<p class="stc-eyebrow">Documentación adjunta</p>
@foreach (\App\Models\Player::tutorUploadDocumentTypes() as $type)
    @php $document = $player->documentByType($type); @endphp
    <p class="login-copy">
        <b>{{ $type }}:</b>
        {{ $document?->fileUrl() ? 'Cargado' : 'Sin cargar' }}
        @if ($document?->fileUrl())
            · <a href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">Ver</a>
        @endif
    </p>
@endforeach
@php $certificate = $player->guardianCertificate(); @endphp
@if ($certificate?->fileUrl())
    <p class="login-copy"><a href="{{ $certificate->fileUrl() }}" target="_blank" rel="noopener">Ver constancia de autorización</a></p>
@endif

<p class="stc-eyebrow">Autorizaciones aceptadas</p>
@foreach (\App\Models\Player::authorizationDocumentTypes() as $type)
    @php $document = $player->documentByType($type); @endphp
    <p class="login-copy"><b>{{ $type }}:</b> {{ $document?->notes ?: 'Aceptada por el tutor.' }}</p>
@endforeach
