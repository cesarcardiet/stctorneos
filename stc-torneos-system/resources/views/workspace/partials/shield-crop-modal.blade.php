<x-ws-modal id="shield-crop" title="Ajustar imagen">
    <div class="ws-cropper">
        <p class="ws-cropper-hint">Arrastrá la imagen para moverla. Con el zoom la acercás o la alejás. Confirmá el recorte antes de guardar.</p>
        <div class="ws-cropper-frame">
            <div class="ws-cropper-stage" data-ws-crop-stage>
                <img data-ws-crop-image alt="Recorte del escudo" draggable="false">
            </div>
        </div>
        <label class="ws-cropper-zoom">
            <span>Zoom</span>
            <input type="range" min="1" max="4" step="0.01" value="1" data-ws-crop-zoom>
        </label>
        <p class="ws-cropper-error" data-ws-crop-error hidden></p>
        <div class="ws-form-actions">
            <button type="button" class="ws-btn ghost" data-ws-close>Cancelar</button>
            <button type="button" class="ws-btn" data-ws-crop-apply>Usar recorte</button>
        </div>
    </div>
</x-ws-modal>
