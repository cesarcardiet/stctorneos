@props(['id', 'title', 'wide' => false, 'setup' => false])

<div {{ $attributes->class('ws-modal') }} data-ws-modal="{{ $id }}" hidden>
    <button type="button" class="ws-modal-backdrop" data-ws-close aria-label="Cerrar"></button>
    <div class="ws-modal-card {{ $wide ? 'is-wide' : '' }} {{ $setup ? 'is-setup' : '' }}" role="dialog" aria-modal="true" aria-labelledby="ws-title-{{ $id }}">
        <header>
            <h2 id="ws-title-{{ $id }}">{{ $title }}</h2>
            <button type="button" class="ws-modal-x" data-ws-close aria-label="Cerrar">✕</button>
        </header>
        <div class="ws-modal-body">
            {{ $slot }}
        </div>
    </div>
</div>
