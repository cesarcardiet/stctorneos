<x-layouts.stc
    title="Revisión documental | STC Torneos"
    active="Documentación"
    heading="Revisión documental"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.documents.index') }}">← Volver a bandeja documental</a>

    <div class="ficha-review-actions doc-review-pills">
        @if ($document->wasSignedByGuardian())
            <span class="ficha-pill approve">Firmado por tutor · constancia generada</span>
            @if ($document->fileUrl())
                <a class="ficha-pill enable" href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">Ver constancia</a>
            @endif
        @else
            <form method="post" action="{{ route('admin.documents.status', $document) }}">
                @csrf
                @method('PATCH')
                <button class="ficha-pill approve" type="submit" name="status" value="approved">Aprobar</button>
            </form>
            <form method="post" action="{{ route('admin.documents.status', $document) }}">
                @csrf
                @method('PATCH')
                <button class="ficha-pill observe" type="submit" name="status" value="observed">Observar</button>
            </form>
            <form method="post" action="{{ route('admin.documents.status', $document) }}">
                @csrf
                @method('PATCH')
                <button class="ficha-pill reject" type="submit" name="status" value="rejected">Rechazar</button>
            </form>
        @endif
        @if ($document->player)
            <a class="ficha-pill enable" href="{{ route('admin.players.show', [$document->player, 'tab' => 'documentacion']) }}">Revisar en ficha del jugador</a>
        @endif
        @if ($document->player && $document->player->status !== 'enabled')
            <form method="post" action="{{ route('admin.documents.enable', $document) }}">
                @csrf
                @method('PATCH')
                <button class="ficha-pill enable" type="submit">Habilitar para jugar</button>
            </form>
        @endif
        @if ($nextPending)
            <a class="ficha-pill enable" href="{{ route('admin.documents.show', $nextPending) }}">Siguiente pendiente</a>
        @endif
    </div>

    <form class="doc-review-layout" method="post" action="{{ route('admin.documents.update', $document) }}">
        @csrf
        @method('PUT')

        <section class="doc-preview-card">
            <h3>Vista previa – {{ $document->previewName() }}</h3>
            <p>Subido {{ $document->uploaded_at?->format('d/m/Y') ?? 'sin fecha' }} · Por {{ $document->uploaded_by_name ?: trim(($document->player?->guardian?->name ?? 'Tutor').' ('.($document->player?->guardian?->relationship ?? 'Padre/Tutor').')') }}</p>
            <div class="doc-preview-frame">
                @if ($document->isImage() && $document->fileUrl())
                    <a href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">
                        <img src="{{ $document->fileUrl() }}" alt="{{ $document->type }}">
                    </a>
                    <p><a href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">Ver imagen</a></p>
                @elseif ($document->fileUrl())
                    @if ($document->isHtmlDocument())
                        <iframe src="{{ $document->fileUrl() }}" title="Constancia"></iframe>
                    @else
                        <iframe src="{{ $document->fileUrl() }}" title="Documento"></iframe>
                    @endif
                    <p><a href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">Ver archivo</a></p>
                @else
                    <strong>[ Documento PDF ]</strong>
                    <span>{{ $document->type }} de {{ $document->player?->fullName() }}</span>
                @endif
            </div>
        </section>

        <aside class="doc-side-card">
            <h3>Checklist validación</h3>
            <ul class="doc-checklist">
                @foreach (\App\Models\PlayerDocument::checklistItems() as $key => $label)
                    @php $value = old('checklist.'.$key, $document->checklistStatus()[$key] ?? 'ok'); @endphp
                    <li data-check-row>
                        <b data-check-icon class="check-{{ $value }}"></b>
                        <span>{{ $label }}</span>
                        <input type="hidden" name="checklist[{{ $key }}]" value="{{ $value }}">
                    </li>
                @endforeach
            </ul>

            <h3>Estado documental</h3>
            @foreach (collect(\App\Models\Player::documentTypes())->map(fn ($type) => $document->player?->documentByType($type))->filter() as $other)
                <div class="doc-status-row">
                    <span>{{ $other->type }}</span>
                    <b class="status-text status-{{ $other->status }}">{{ $other->statusLabel() }}</b>
                </div>
            @endforeach
            <input type="hidden" name="status" value="{{ $document->status }}">

            <h3>Habilitación</h3>
            <ul class="player-data-list">
                <li><span>Documentación</span><strong>{{ $document->player?->documentationSummary() ?? '—' }}</strong></li>
                <li><span>Autorizaciones</span><strong>{{ $document->player?->authorizationSummary() ?? '—' }}</strong></li>
                <li><span>Elegibilidad</span><strong>{{ $document->player?->eligibilityLabel() ?? '—' }}</strong></li>
            </ul>
        </aside>

        <section class="doc-notes-card">
            <label>Motivo de observación o rechazo
                <textarea name="notes" rows="3" placeholder="Ej: El apto médico está vencido. Solicitar renovación al padre/tutor.">{{ old('notes', $document->notes) }}</textarea>
            </label>
            <label>Vencimiento <input type="date" name="expires_at" value="{{ old('expires_at', optional($document->expires_at)->format('Y-m-d')) }}" min="1900-01-01" max="2100-12-31"></label>
            <p>{{ $document->expirationLabel() }}</p>
            <button class="ficha-save" type="submit">Guardar revisión</button>
        </section>
    </form>

    <section class="delegation-list-card" style="margin-top: 1.2rem;">
        <header><h3>Historial de revisión</h3></header>
        <div class="delegation-figma-table history-figma-table">
            <div class="table-head"><span>Fecha</span><span>Acción</span><span>Detalle</span></div>
            @forelse ($history as $log)
                <div class="table-row">
                    <span>{{ $log->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                    <span>{{ $log->action }}</span>
                    <span>{{ $log->description }}</span>
                </div>
            @empty
                <div class="category-empty-state"><strong>Todavía no hay revisiones guardadas para este documento.</strong></div>
            @endforelse
        </div>
    </section>

    <script>
        document.querySelectorAll('[data-check-row]').forEach((row) => {
            const input = row.querySelector('input[type="hidden"]');
            const icon = row.querySelector('[data-check-icon]');
            const cycle = { ok: 'warn', warn: 'fail', fail: 'ok' };

            row.addEventListener('click', () => {
                input.value = cycle[input.value] || 'ok';
                icon.className = 'check-' + input.value;
            });
        });
    </script>
</x-layouts.stc>
