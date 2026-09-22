@php
    $documentTypes = [\App\Models\Player::guardianCertificateType()];
    $certificate = $player->guardianCertificate();
@endphp

<ul class="player-docs-card">
    @foreach ($documentTypes as $type)
        @php
            $document = $player->documentByType($type) ?? $certificate;
        @endphp
        <li class="ws-doc-row">
            @php $url = $document?->fileUrl(); @endphp
            @if ($document?->isImage() && $url)
                <a class="ws-doc-thumb" href="{{ $url }}" target="_blank" rel="noopener" title="Ver {{ $type }}">
                    <img src="{{ $url }}" alt="{{ $type }}">
                </a>
            @else
                <span class="ws-doc-thumb is-empty" aria-hidden="true"></span>
            @endif
            <div>
                <strong>{{ $type }}</strong>
                <span>
                    @if ($url)
                        Constancia generada
                        @if ($document?->original_name)
                            · {{ $document->original_name }}
                        @endif
                    @else
                        Pendiente de generación
                    @endif
                </span>
            </div>
            <div class="ws-doc-actions">
                @if ($url)
                    <a href="{{ $url }}" target="_blank" rel="noopener">Ver</a>
                    @if ($document)
                        <a href="{{ route('workspace.player.documents.download', $document) }}">Descargar</a>
                    @endif
                @else
                    <span class="ws-muted">Sin archivo</span>
                @endif
            </div>
        </li>
    @endforeach
</ul>
