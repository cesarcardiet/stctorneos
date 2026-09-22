@php
    $document = $document ?? null;
    $allowUpload = $allowUpload ?? false;
    $allowReview = $allowReview ?? false;
    $label = $label ?? ($document?->type ?? $type ?? '');
    $isAuthorizationArea = in_array($type, \App\Models\Player::authorizationDocumentTypes(), true)
        || $type === \App\Models\Player::guardianCertificateType();
    $status = $document?->wasSignedByGuardian()
        ? ($document->type === \App\Models\Player::guardianCertificateType() ? 'Constancia generada' : 'Firmado por tutor')
        : ($isAuthorizationArea ? 'Pendiente de firma del tutor' : ($document?->statusLabel() ?? 'Pendiente'));
    $url = $document?->fileUrl();
    $isImage = $document?->isImage() && $url;
    $statusTone = match ($document?->status ?? 'pending') {
        'approved' => 'is-ok',
        'rejected' => 'is-bad',
        'observed' => 'is-warn',
        default => 'is-pending',
    };
    if ($document?->wasSignedByGuardian()) {
        $statusTone = 'is-ok';
    }
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
                title="Ver {{ $label }}"
            >
                <img src="{{ $url }}" alt="{{ $label }}">
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
            @if ($document?->notes && $isAuthorizationArea)
                <small class="ws-muted">{{ $document->notes }}</small>
            @endif
        </div>
    </div>
    <div class="ws-doc-card-actions">
        @if ($url && ! $isImage)
            <a class="ws-btn ghost" href="{{ $url }}" target="_blank" rel="noopener">{{ $document?->isHtmlDocument() ? 'Ver constancia' : 'Ver archivo' }}</a>
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
        @if ($canEdit && $allowReview && $document && ! $isAuthorizationArea)
            <form method="post" action="{{ route('workspace.categories.documents.review', [$category, $document]) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $document->status === 'approved' ? 'observed' : 'approved' }}">
                <button type="submit" class="ws-btn ghost">{{ $document->status === 'approved' ? 'Observar' : 'Aprobar' }}</button>
            </form>
        @endif
    </div>
</li>
