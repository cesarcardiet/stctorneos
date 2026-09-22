@php
    use App\Models\Player;
    use App\Models\PlayerDocument;

    $reason = $player->observation_reason ?: $player->notes;
    $docTypes = Player::documentTypes();
    $documentsByType = $player->documents->keyBy('type');
    $pendingDocCount = $player->documents->whereIn('status', ['pending', 'observed', 'rejected'])->count();
@endphp

<article class="ws-inscription-card is-{{ $tone ?? 'waiting' }}">
    <header class="ws-inscription-card-head">
        <a class="ws-inscription-card-player" href="{{ route('workspace.categories.players.show', [$category, $player]) }}">
            <img src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}" data-ws-rank-photo>
            <div>
                <strong>{{ $player->fullName() }}</strong>
                <span>{{ $player->team?->name }}</span>
                @if ($player->guardian?->name)
                    <small>Tutor: {{ $player->guardian->name }}</small>
                @endif
            </div>
        </a>
        <div class="ws-inscription-card-meta">
            <span class="ws-inscription-badge is-{{ $tone ?? 'waiting' }}">{{ $player->inscriptionBandLabel() }}</span>
            <small>{{ $player->updated_at?->format('d/m/Y H:i') }}</small>
            @if ($pendingDocCount > 0)
                <span class="ws-inscription-doc-count">{{ $pendingDocCount }} doc. por revisar</span>
            @endif
        </div>
    </header>

    @if ($reason)
        <p class="ws-inscription-card-reason">{{ $reason }}</p>
    @endif

    <section class="ws-inscription-card-docs">
        <div class="ws-inscription-doc-grid">
            @foreach ($docTypes as $type)
                @php
                    $document = $documentsByType->get($type);
                    $url = $document?->fileUrl();
                    $isImage = $document?->isImage() && $url;
                    $isAuthorization = in_array($type, Player::authorizationDocumentTypes(), true);
                    $docStatus = $document?->status ?? 'pending';
                    $docTone = match ($docStatus) {
                        'approved' => 'approved',
                        'rejected' => 'rejected',
                        'observed' => 'observed',
                        default => $url ? 'pending' : 'empty',
                    };
                    if ($document?->wasSignedByGuardian()) {
                        $docTone = 'approved';
                    }
                @endphp
                <article class="ws-inscription-doc-card is-{{ $docTone }}">
                    @if ($isImage)
                        <button
                            type="button"
                            class="ws-inscription-doc-preview"
                            data-ws-doc-preview
                            data-preview-src="{{ $url }}"
                            data-preview-title="{{ $type }}"
                            data-preview-kind="image"
                            title="Ver {{ $type }}"
                        >
                            <img src="{{ $url }}" alt="{{ $type }}">
                        </button>
                    @elseif ($url)
                        <button
                            type="button"
                            class="ws-inscription-doc-preview is-file"
                            data-ws-doc-preview
                            data-preview-src="{{ $url }}"
                            data-preview-title="{{ $type }}"
                            data-preview-kind="{{ $document?->isHtmlDocument() ? 'html' : 'file' }}"
                            title="Ver {{ $type }}"
                        >
                            <span>{{ $document?->isHtmlDocument() ? 'HTML' : 'PDF' }}</span>
                            <small>Ver archivo</small>
                        </button>
                    @else
                        <div class="ws-inscription-doc-preview is-empty">
                            <span>Sin archivo</span>
                        </div>
                    @endif

                    <div class="ws-inscription-doc-body">
                        <strong>{{ $type }}</strong>
                        <span class="ws-inscription-doc-status">
                            @if ($document?->wasSignedByGuardian())
                                {{ $document->type === Player::guardianCertificateType() ? 'Constancia generada' : 'Firmado por tutor' }}
                            @elseif ($isAuthorization && ! $url)
                                Pendiente de firma
                            @else
                                {{ $document?->statusLabel() ?? 'Pendiente' }}
                            @endif
                        </span>
                        @if ($document?->notes && in_array($docStatus, ['observed', 'rejected'], true))
                            <small class="ws-inscription-doc-note">{{ $document->notes }}</small>
                        @endif
                    </div>

                    <div class="ws-inscription-doc-actions">
                        @if ($url)
                            <button
                                type="button"
                                class="ws-btn ghost"
                                data-ws-doc-preview
                                data-preview-src="{{ $url }}"
                                data-preview-title="{{ $type }}"
                                data-preview-kind="{{ $isImage ? 'image' : ($document?->isHtmlDocument() ? 'html' : 'file') }}"
                            >Ver</button>
                        @endif
                        @if (! empty($canReview) && $document && $url && ! $document->wasSignedByGuardian() && PlayerDocument::requiresClubReviewForType($type))
                            @if ($docStatus !== 'approved')
                                <form method="post" action="{{ route('workspace.categories.documents.review', [$category, $document]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="ws-btn">Aprobar</button>
                                </form>
                            @endif
                            @if ($docStatus !== 'rejected')
                                <details class="ws-inscription-doc-reject">
                                    <summary class="ws-btn ghost danger">Rechazar</summary>
                                    <form method="post" action="{{ route('workspace.categories.documents.review', [$category, $document]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="rejected">
                                        <label>Motivo
                                            <input type="text" name="notes" maxlength="500" placeholder="Ej: Ilegible">
                                        </label>
                                        <button type="submit" class="ws-btn danger">Confirmar</button>
                                    </form>
                                </details>
                            @endif
                            @if ($docStatus === 'approved')
                                <form method="post" action="{{ route('workspace.categories.documents.review', [$category, $document]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="observed">
                                    <button type="submit" class="ws-btn ghost">Observar</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <footer class="ws-inscription-card-foot">
        <a class="ws-btn ghost" href="{{ route('workspace.categories.players.show', [$category, $player]) }}">Ver ficha</a>
        @if ($player->can_edit_ficha ?? false)
            <a class="ws-btn ghost" href="{{ route('workspace.categories.players.edit', [$category, $player]) }}">Editar ficha</a>
        @endif
        @if (! empty($canReview))
            @if (($tone ?? 'waiting') === 'waiting')
                <form method="post" action="{{ route('workspace.categories.inscriptions.review', [$category, $player]) }}">
                    @csrf
                    <input type="hidden" name="decision" value="approve">
                    <button type="submit" class="ws-btn">Aprobar ficha</button>
                </form>
                <details class="ws-inscription-reject">
                    <summary class="ws-btn ghost danger">Rechazar ficha</summary>
                    <form method="post" action="{{ route('workspace.categories.inscriptions.review', [$category, $player]) }}">
                        @csrf
                        <input type="hidden" name="decision" value="reject">
                        <label>Motivo del rechazo
                            <input type="text" name="notes" maxlength="500" placeholder="Ej: Documentación incompleta">
                        </label>
                        <button type="submit" class="ws-btn danger">Confirmar rechazo</button>
                    </form>
                </details>
            @else
                <form method="post" action="{{ route('workspace.categories.inscriptions.review', [$category, $player]) }}">
                    @csrf
                    <input type="hidden" name="decision" value="approve">
                    <button type="submit" class="ws-btn">Reabrir / aprobar ficha</button>
                </form>
            @endif
        @endif
    </footer>
</article>
