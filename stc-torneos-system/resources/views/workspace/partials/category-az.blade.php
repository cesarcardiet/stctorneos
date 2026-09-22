@props([
    'letters' => [],
])

<div class="ws-az" data-ws-cat-az>
    <button type="button" class="is-on" data-letter="">Todas</button>
    @foreach ($letters as $letter)
        <button type="button" data-letter="{{ $letter }}">{{ $letter === '#' ? '0-9' : $letter }}</button>
    @endforeach
</div>
