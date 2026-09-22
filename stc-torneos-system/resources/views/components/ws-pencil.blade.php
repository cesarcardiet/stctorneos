@props(['open', 'label'])

<button type="button" class="ws-pencil" data-ws-open="{{ $open }}" aria-label="{{ $label }}" title="{{ $label }}">
    <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
        <path d="M12 20h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>
