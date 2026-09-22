@props([
    'href' => null,
    'src',
    'alt' => '',
    'shape' => 'square',
])

<span {{ $attributes->class(['entity-cell']) }}>
    <img class="entity-thumb entity-thumb--{{ $shape }}" src="{{ $src }}" alt="{{ $alt }}">
    <span class="entity-cell-copy">
        @if ($href)
            <a class="row-title-link" href="{{ $href }}">{{ $slot }}</a>
        @else
            <strong class="row-title-link">{{ $slot }}</strong>
        @endif
        @isset($subtitle)
            <small>{{ $subtitle }}</small>
        @endisset
    </span>
</span>
