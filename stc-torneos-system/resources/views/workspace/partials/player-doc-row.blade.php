@php
    $document = $document ?? null;
    $allowUpload = $allowUpload ?? false;
    $allowReview = $allowReview ?? false;
    $label = $label ?? ($document?->type ?? $type ?? '');
    $isAuthorizationArea = in_array($type, \App\Models\Player::authorizationDocumentTypes(), true)
        || $type === \App\Models\Player::guardianCertificateType();
    $url = $document?->fileUrl();
    $isImage = (bool) ($document?->isImage() && $url);
    $isHtml = (bool) ($document?->isHtmlDocument() && $url);
    $status = $document?->wasSignedByGuardian()
        ? ($document->type === \App\Models\Player::guardianCertificateType() ? 'Constancia generada' : 'Firmado por tutor')
        : ($url
            ? 'Aprobado'
            : ($isAuthorizationArea ? 'Pendiente de firma del tutor' : 'Pendiente'));
    $statusTone = $url || $document?->wasSignedByGuardian() ? 'is-ok' : 'is-pending';
@endphp
<li class="ws-doc-card">
    <div class="ws-doc-card-main">
        @if ($isImage)
            <button
                type="button"
                class="ws-doc-thumb"
                data-ws-doc-preview
                data-preview-src="{{ $url }}"
                data-preview-title="{{ $label }}"
                data-preview-kind="image"
                title="Ver {{ $label }}"
            >
                <img src="{{ $url }}" alt="{{ $label }}">
            </button>
        @elseif ($url)
            <button
                type="button"
                class="ws-doc-thumb is-file"
                data-ws-doc-preview
                data-preview-src="{{ $url }}"
                data-preview-title="{{ $label }}"
                data-preview-kind="{{ $isHtml ? 'html' : 'file' }}"
                title="Ver {{ $label }}"
            >
                <span>{{ $isHtml ? 'HTML' : 'PDF' }}</span>
            </button>
        @else
            <span class="ws-doc-thumb is-empty" aria-hidden="true"></span>
        @endif
        <div class="ws-doc-card-copy">
            <strong>{{ $label }}</strong>
            <span class="ws-status-pill {{ $statusTone }}">{{ $status }}</span>
            @if ($document?->original_name)
                <small class="ws-muted">{{ $document->original_name }}</small>
            @endif
            @if ($document?->notes && in_array($document->status, ['observed', 'rejected'], true))
                <small class="ws-muted">{{ $document->notes }}</small>
            @endif
        </div>
    </div>
    <div class="ws-doc-card-actions">
        @if ($url)
            <button
                type="button"
                class="ws-btn ghost"
                data-ws-doc-preview
                data-preview-src="{{ $url }}"
                data-preview-title="{{ $label }}"
                data-preview-kind="{{ $isImage ? 'image' : ($isHtml ? 'html' : 'file') }}"
            >Ver</button>
        @endif
        @if ($canEdit && $allowUpload)
            <form class="ws-doc-upload" method="post" action="{{ route('workspace.categories.players.documents', [$category, $player]) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <label class="ws-btn ghost">
                    {{ $url ? 'Cambiar' : 'Subir' }}
                    <input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/gif" required onchange="this.form.submit()">
                </label>
            </form>
        @endif
    </div>
</li>
