@php
    $canReview = ($canApprove ?? false) || auth()->user()?->hasPermission('players.approve');
    $docsComplete = $player->documentationSummary() === 'Completa';
    $pendingReviewCount = 0;
    foreach (\App\Models\Player::documentTypes() as $type) {
        $doc = $player->documentByType($type);
        if (! $doc || ! $doc->fileUrl() || $doc->wasSignedByGuardian() || $doc->status === 'approved') {
            continue;
        }
        if (\App\Models\PlayerDocument::requiresClubReviewForType($type)
            || ! in_array($type, \App\Models\Player::authorizationDocumentTypes(), true)) {
            $pendingReviewCount++;
        }
    }
@endphp

<section class="player-doc-review-panel">
    <header class="player-doc-review-head">
        <div>
            <h3>Aprobación y habilitación</h3>
            <p class="ws-muted">Revisá los archivos con <strong>Ver</strong>. Cuando esté todo en orden, usá un solo botón para aprobar la documentación completa.</p>
        </div>
        <div class="player-doc-review-summary">
            <span @class(['admin-status', 'is-approved' => $docsComplete, 'is-observed' => ! $docsComplete])>{{ $player->documentationSummary() }}</span>
            <span @class(['admin-status', 'is-approved' => $player->status === 'enabled', 'is-blocked' => $player->status !== 'enabled'])>{{ $player->eligibilityLabel() }}</span>
        </div>
    </header>

    @if ($canReview)
        <div class="player-doc-approve-all">
            <form method="post" action="{{ route('admin.players.documents.approve-all', $player) }}" data-confirm="¿Aprobar toda la documentación cargada de {{ $player->fullName() }}?">
                @csrf
                @method('PATCH')
                <button type="submit" class="primary-action" @disabled($pendingReviewCount === 0)>
                    Aprobar toda la documentación
                    @if ($pendingReviewCount > 0)
                        ({{ $pendingReviewCount }})
                    @endif
                </button>
            </form>
            @if ($pendingReviewCount === 0)
                <p class="ws-muted">No hay documentos pendientes de aprobación, o faltan archivos por cargar.</p>
            @else
                <p class="ws-muted">Se aprueban juntos DNI, cobertura, foto y demás archivos revisables. Las firmas del tutor no se tocan.</p>
            @endif
        </div>
    @endif

    <div class="player-docs-grid player-doc-review-grid">
        @foreach (\App\Models\Player::documentTypes() as $type)
            @php
                $document = $player->documentByType($type);
                $isAuthorization = in_array($type, \App\Models\Player::authorizationDocumentTypes(), true)
                    || $type === \App\Models\Player::guardianCertificateType();
                $signedByGuardian = $document?->wasSignedByGuardian() ?? false;
                $url = $document?->fileUrl();
                $isImage = (bool) ($document?->isImage() && $url);
                $isHtml = (bool) ($document?->isHtmlDocument() && $url);
                $previewKind = $isImage ? 'image' : ($isHtml ? 'html' : 'file');
            @endphp
            <article @class(['player-doc-review-card', 'is-'.$document?->status, 'is-empty' => ! $document])>
                <header>
                    <strong>{{ $type }}</strong>
                    @if ($document)
                        <span @class(['status-text', 'status-'.$document->status])>{{ $signedByGuardian ? 'Firmado por tutor' : $document->statusLabel() }}</span>
                    @else
                        <span class="status-text status-pending">Sin cargar</span>
                    @endif
                </header>

                @if ($url)
                    @if ($isImage)
                        <button
                            type="button"
                            class="player-doc-preview-thumb"
                            data-ws-doc-preview
                            data-preview-src="{{ $url }}"
                            data-preview-title="{{ $type }}"
                            data-preview-kind="image"
                        >
                            <img src="{{ $url }}" alt="{{ $type }}">
                        </button>
                    @else
                        <button
                            type="button"
                            class="player-doc-file-link stc-btn-new"
                            data-ws-doc-preview
                            data-preview-src="{{ $url }}"
                            data-preview-title="{{ $type }}"
                            data-preview-kind="{{ $previewKind }}"
                        >Ver archivo</button>
                    @endif
                @endif

                @if ($document?->notes)
                    <p class="player-doc-note">{{ $document->notes }}</p>
                @endif

                <div class="player-doc-review-actions">
                    @if ($url)
                        <button
                            type="button"
                            class="stc-btn-new"
                            data-ws-doc-preview
                            data-preview-src="{{ $url }}"
                            data-preview-title="{{ $type }}"
                            data-preview-kind="{{ $previewKind }}"
                        >Ver</button>
                    @endif

                    @if ($canReview && $document && ! $signedByGuardian && ($document->requiresClubApproval() || ! $isAuthorization))
                        <details class="player-doc-review-details">
                            <summary class="ghost-action">Observar</summary>
                            <form method="post" action="{{ route('admin.players.documents.review', [$player, $document]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="observed">
                                <textarea name="notes" rows="2" placeholder="Motivo de la observación" required>{{ $document->notes }}</textarea>
                                <button type="submit" class="ghost-action">Guardar observación</button>
                            </form>
                        </details>
                        <details class="player-doc-review-details">
                            <summary class="ghost-action">Rechazar</summary>
                            <form method="post" action="{{ route('admin.players.documents.review', [$player, $document]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="rejected">
                                <textarea name="notes" rows="2" placeholder="Motivo del rechazo" required>{{ $document->notes }}</textarea>
                                <button type="submit" class="ghost-action">Confirmar rechazo</button>
                            </form>
                        </details>
                    @elseif ($signedByGuardian && $url)
                        <span class="ws-muted">Constancia del tutor</span>
                    @elseif ($isAuthorization && ! $document)
                        <p class="ws-muted">Pendiente de firma del tutor.</p>
                    @elseif (! $document)
                        <p class="ws-muted">Todavía no cargaron este archivo.</p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @if ($canReview)
        <footer class="player-doc-review-foot">
            @if ($player->status !== 'enabled')
                <form method="post" action="{{ route('admin.players.enable', $player) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="primary-action" @disabled(! $docsComplete)>
                        Habilitar para jugar
                    </button>
                </form>
                @unless ($docsComplete)
                    <p class="ws-muted">Primero aprobá la documentación (botón de arriba). Después habilitás al jugador.</p>
                @endunless
            @else
                <p class="system-alert tone-green">Jugador habilitado para jugar.</p>
            @endif

            @include('admin.players.partials.status-actions', ['player' => $player])
        </footer>
    @else
        <footer class="player-doc-review-foot">
            <p class="ws-muted">Tu usuario puede ver la documentación, pero no tiene permiso <code>players.approve</code> para aprobar. Pedile acceso a un Admin General / Admin Torneo.</p>
        </footer>
    @endif
</section>
