@php
    $src = $src ?? null;
    $saveUrl = $saveUrl ?? null;
    $logos = $logos ?? [];
    $compact = $compact ?? false;
    $currentPath = $currentPath ?? '';
@endphp

<div class="ws-cat-banner-field" @if (!empty($categoryId)) data-ws-banner-category="{{ $categoryId }}" @endif @if (!empty($identity)) data-ws-banner-identity="{{ $identity }}" @endif>
    <x-ws-shield-field
        :src="$src"
        :save-url="$saveUrl"
        :compact="$compact"
        heading="Imagen de la categoría"
        hint="Por defecto usa la imagen del torneo. Después la podés cambiar."
    />
    <input type="hidden" name="image_path" value="{{ $currentPath }}" data-ws-banner-path>
    @if (count($logos))
        <div class="ws-logo-picks" @if ($saveUrl) data-ws-banner-picks="{{ $saveUrl }}" @endif>
            <span>Logos del torneo</span>
            <div class="ws-logo-picks-row">
                @foreach ($logos as $logo)
                    <button
                        type="button"
                        data-ws-banner-pick
                        data-path="{{ $logo['path'] }}"
                        data-url="{{ $logo['url'] }}"
                        title="{{ $logo['name'] }}"
                    >
                        <img src="{{ $logo['url'] }}" alt="{{ $logo['name'] }}">
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
