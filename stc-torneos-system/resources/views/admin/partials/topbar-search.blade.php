@php
    $searchAction = \App\Support\AdminNavigation::searchAction($active ?? null);
    $searchKeys = \App\Support\AdminNavigation::searchPreserveKeys($active ?? null);
@endphp
<form
    class="stc-search"
    method="get"
    action="{{ $searchAction }}"
    role="search"
    data-suggest-url="{{ route('admin.search.suggest') }}"
    data-scope="{{ $active ?? '' }}"
>
    @foreach ($searchKeys as $key)
        @if (filled(request($key)))
            <input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
        @endif
    @endforeach
    <div class="stc-search-field">
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Buscar en esta pantalla"
            aria-label="Buscar en esta pantalla"
            aria-autocomplete="list"
            autocomplete="off"
            spellcheck="false"
        >
        <div class="stc-suggest" hidden role="listbox" aria-label="Sugerencias de búsqueda"></div>
    </div>
    <button type="submit">Buscar</button>
</form>
