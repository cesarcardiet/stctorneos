@php
    $url = $document?->fileUrl();
    $uploaded = filled($url);
    $isRequired = $required ?? false;
@endphp
<li class="ficha-doc-row" data-ficha-doc data-doc-type="{{ $type }}" data-doc-uploaded="{{ $uploaded ? '1' : '0' }}" @if($isRequired) data-doc-must="1" @endif>
    <div class="ficha-doc-row-head">
        <strong>{{ $type }}@if($isRequired) * @endif</strong>
        <span class="ficha-doc-status">{{ $uploaded ? 'Cargado' : 'Pendiente' }}</span>
    </div>
    @if ($uploaded && $document?->isImage())
        <img class="ficha-doc-preview" src="{{ $url }}" alt="{{ $type }}" data-doc-preview>
    @else
        <img class="ficha-doc-preview" alt="{{ $type }}" data-doc-preview hidden>
    @endif
    @if ($uploaded)
        <p class="ficha-doc-view"><a href="{{ $url }}" target="_blank" rel="noopener">Ver archivo actual</a></p>
    @endif
    <label class="ficha-doc-upload">
        <span>{{ $uploaded ? 'Reemplazar archivo' : 'Subir archivo' }}</span>
        <input
            type="file"
            name="document_files[{{ $type }}]"
            accept="image/png,image/jpeg,image/webp,image/gif"
            data-doc-input="{{ $type }}"
            @if($isRequired && ! $uploaded) data-doc-required="1" @endif
        >
    </label>
</li>
