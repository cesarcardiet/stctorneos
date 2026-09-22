<article class="sheets-editor-card">
    <h2 class="sheets-result-title">{{ $sheet->scoreTitle() }}</h2>
    <p class="sheets-result-meta">{{ $sheet->match?->stage }} · {{ $sheet->match?->category?->name }} · {{ $sheet->match?->field?->name }}</p>

    <div class="sheets-check-list">
        <div class="sheets-check is-ok">
            <span>Datos del partido</span>
            <b>Completo</b>
        </div>
        <div class="sheets-check is-ok">
            <span>Eventos</span>
            <b>{{ $sheet->events->count() }} cargados</b>
        </div>
        <div @class(['sheets-check', 'is-ok' => $sheet->incidents->isNotEmpty(), 'is-warn' => $sheet->incidents->isEmpty()])>
            <span>Incidencias</span>
            <b>{{ $sheet->incidents->count() }} aplicada{{ $sheet->incidents->count() === 1 ? '' : 's' }}</b>
        </div>
        <div @class(['sheets-check', 'is-ok' => $sheet->pendingSignaturesCount() === 0, 'is-warn' => $sheet->pendingSignaturesCount() > 0])>
            <span>Firmas</span>
            <b>{{ $sheet->pendingSignaturesCount() }} pendiente{{ $sheet->pendingSignaturesCount() === 1 ? '' : 's' }}</b>
        </div>
    </div>

    @if ($sheet->locked)
        <p class="sheets-locked-note">La planilla está cerrada y el resultado quedó publicado.</p>
    @else
        <div class="fields-form-actions sheets-close-actions">
            <form method="post" action="{{ route('admin.sheets.publish', $sheet) }}">
                @csrf
                <button type="submit">Publicar resultado</button>
            </form>
            <form method="post" action="{{ route('admin.sheets.draft', $sheet) }}">
                @csrf
                <button class="sheets-secondary-btn" type="submit">Guardar borrador</button>
            </form>
            <a class="sheets-secondary-btn sheets-close-pdf" href="{{ route('admin.sheets.pdf', $sheet) }}" target="_blank" rel="noopener">Planilla PDF</a>
        </div>
    @endif
</article>
