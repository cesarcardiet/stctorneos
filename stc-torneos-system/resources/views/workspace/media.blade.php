<x-layouts.workspace
    :title="$title"
    heading="Fotos, videos y noticias"
    :subheading="$category->name"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <div class="ws-toolbar">
        <p class="ws-muted">Contenido de esta categoría.</p>
        @if ($canEdit)
            <button type="button" class="ws-fab" data-ws-open="add-media">+</button>
        @endif
    </div>

    @forelse ($posts as $post)
        <article class="ws-card">
            <small>{{ $post->typeLabel() }}</small>
            <h3>{{ $post->title }}</h3>
            <p>{{ $post->summary ?: $post->body }}</p>
        </article>
    @empty
        <div class="ws-empty">Aún no hay medios. El + carga galería, noticia o un enlace.</div>
    @endforelse

    @if ($canEdit)
        <x-ws-modal id="add-media" title="Agregar contenido">
            <x-ws-form :action="route('workspace.categories.media.store', $category)" submit="Publicar">
                <label>Tipo
                    <select name="kind">
                        <option value="news">Crear noticia</option>
                        <option value="link">Añadir enlace</option>
                        <option value="youtube">YouTube</option>
                        <option value="gallery">Galería</option>
                    </select>
                </label>
                <label>Título <input type="text" name="title" required></label>
                <label>Texto o URL <textarea name="body" rows="4"></textarea></label>
            </x-ws-form>
        </x-ws-modal>
    @endif
</x-layouts.workspace>
