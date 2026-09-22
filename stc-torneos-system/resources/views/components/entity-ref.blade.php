@props([
    'href' => null,
    'src' => null,
    'alt' => '',
    'shape' => 'square',
])

@php
    $tag = $href ? 'a' : 'span';
@endphp

<{{ $tag }} {{ $attributes->class(['entity-ref'])->merge($href ? ['href' => $href] : []) }}>
    @if ($src)
        <img class="entity-thumb entity-thumb--sm entity-thumb--{{ $shape }}" src="{{ $src }}" alt="{{ $alt }}">
    @endif
    <span>{{ $slot }}</span>
</{{ $tag }}>
