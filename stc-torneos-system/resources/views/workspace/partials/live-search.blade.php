@props([
    'placeholder' => 'Escribí para filtrar…',
    'label' => 'Buscar',
    'empty' => 'No hay coincidencias con esa búsqueda.',
])

<div class="ws-live-search">
    <label class="ws-live-search-label" for="{{ $id ?? '' }}">{{ $label }}</label>
    <div class="ws-live-search-field">
        <span class="ws-live-search-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="M20 20l-3.5-3.5"></path>
            </svg>
        </span>
        <input
            @if (! empty($id)) id="{{ $id }}" @endif
            type="search"
            data-live-search
            @if (! empty($name)) name="{{ $name }}" value="{{ $value ?? '' }}" @endif
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            spellcheck="false"
        >
    </div>
    <p class="ws-live-empty" data-live-empty hidden>{{ $empty }}</p>
</div>
