<x-layouts.workspace
    :title="$title"
    heading="Fixture"
    :subheading="$tournament->name.' · '.$category->name"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <article class="ws-card ws-fixture-public">
        <header class="ws-card-head">
            <h3>Fixture publicado</h3>
            <nav class="ws-row-actions">
                <a href="{{ route('workspace.categories.standings', $category) }}">Clasificación</a>
                <a href="{{ route('workspace.categories.teams', $category) }}">Equipos</a>
            </nav>
        </header>

        <figure class="ws-fixture-poster">
            <img src="{{ $fixtureImageUrl }}" alt="Fixture de {{ $category->name }}">
        </figure>

        <p class="ws-muted">Vista de consulta. El organizador carga y actualiza el fixture; desde acá solo ves la imagen publicada.</p>
    </article>
</x-layouts.workspace>
