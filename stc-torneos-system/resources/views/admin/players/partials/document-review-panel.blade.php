@php
    $canReview = ($canApprove ?? false) || auth()->user()?->hasPermission('players.approve');
    $docsComplete = $player->documentationSummary() === 'Completa';
@endphp

<section class="player-doc-review-panel">
    <header class="player-doc-review-head">
        <div>
            <h3>Aprobación y habilitación</h3>
            <p class="ws-muted">Desde acá aprobás cada documento y habilitás al jugador. No hace falta ir a otro módulo.</p>
        </div>
        <div class="player-doc-review-summary">
            <span @class(['admin-status', 'is-approved' => $docsComplete, 'is-observed' => ! $docsComplete])>{{ $player->documentationSummary() }}</span>
            <span @class(['admin-status', 'is-approved' => $player->status === 'enabled', 'is-blocked' => $player->status !== 'enabled'])>{{ $player->eligibilityLabel() }}</span>
        </div>
    </header>

    <div class="player-docs-grid player-doc-review-grid">
        @foreach (\App\Models\Player::documentTypes() as $type)
            @php
                $document = $player->documentByType($type);
                $isAuthorization = in_array($type, \App\Models\Player::authorizationDocumentTypes(), true)
                    || $type === \App\Models\Player::guardianCertificateType();
                $signedByGuardian = $document?->wasSignedByGuardian() ?? false;
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

                @if ($document?->fileUrl())
                    @if ($document->isImage())
                        <a href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">
                            <img src="{{ $document->fileUrl() }}" alt="{{ $type }}">
                        </a>
                    @else
                        <a class="player-doc-file-link stc-btn-new" href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">Ver archivo</a>
                    @endif
                @endif

                @if ($document?->notes)
                    <p class="player-doc-note">{{ $document->notes }}</p>
                @endif

                @if ($canReview && $document && ! $signedByGuardian && ($document->requiresClubApproval() || ! $isAuthorization))
                    <div class="player-doc-review-actions">
                        <form method="post" action="{{ route('admin.players.documents.review', [$player, $document]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="approved">
                            <button type="submit" class="stc-btn-new" @disabled($document->status === 'approved')>
                                {{ $document->status === 'approved' ? 'Aprobado' : 'Aprobar' }}
                            </button>
                        </form>
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
                    </div>
                @elseif ($signedByGuardian && $document?->fileUrl())
                    <a class="stc-btn-new" href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">Ver constancia del tutor</a>
                @elseif ($isAuthorization && ! $document)
                    <p class="ws-muted">Pendiente de firma del tutor.</p>
                @elseif (! $document)
                    <p class="ws-muted">Todavía no cargaron este archivo.</p>
                @endif
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
                    <p class="ws-muted">Aprobá todos los documentos obligatorios antes de habilitar.</p>
                @endunless
            @else
                <p class="system-alert tone-green">Jugador habilitado para jugar.</p>
            @endif

            @include('admin.players.partials.status-actions', ['player' => $player])
        </footer>
    @endif
</section>
