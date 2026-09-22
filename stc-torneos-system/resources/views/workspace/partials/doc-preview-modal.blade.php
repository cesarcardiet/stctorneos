<div class="ws-modal" data-ws-modal="doc-preview" hidden>
    <button type="button" class="ws-modal-backdrop" data-ws-close aria-label="Cerrar"></button>
    <div class="ws-modal-card is-wide ws-doc-preview-card" role="dialog" aria-modal="true">
        <header>
            <h2 data-ws-doc-preview-title>Vista previa</h2>
            <button type="button" class="ws-modal-x" data-ws-close aria-label="Cerrar">✕</button>
        </header>
        <div class="ws-modal-body ws-doc-preview-body">
            <img data-ws-doc-preview-image src="" alt="" hidden>
            <iframe data-ws-doc-preview-frame title="Documento" hidden></iframe>
            <p class="ws-muted" data-ws-doc-preview-empty hidden>No se pudo cargar la vista previa.</p>
        </div>
        <footer class="ws-doc-preview-foot">
            <a class="ws-btn ghost" data-ws-doc-preview-open href="#" target="_blank" rel="noopener" hidden>Abrir en pestaña</a>
            <button type="button" class="ws-btn" data-ws-close>Cerrar</button>
        </footer>
    </div>
</div>
