<x-layouts.stc
    :title="$post->title.' | STC Torneos'"
    active="Comunicaciones"
    heading="Detalle de contenido"
    :subheading="$post->typeLabel().' · '.$post->statusLabel()"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.communications.index') }}">← Volver al listado</a>

    <section class="users-detail-layout">
        <article class="users-form-card">
            <div class="comms-cover" style="--comms-cover: url('{{ $post->coverUrl() }}')"></div>
            <h3>{{ $post->title }}</h3>
            <p>{{ $post->summary ?: 'Sin bajada.' }}</p>
            <div class="category-info-list">
                <span><small>Tipo</small><strong>{{ $post->typeLabel() }}</strong></span>
                <span><small>Audiencia</small><strong>{{ $post->audienceLabel() }}</strong></span>
                <span><small>Torneo</small><strong>{{ $post->tournament?->name ?? 'Todos' }}</strong></span>
                <span><small>Autor</small><strong>{{ $post->author?->name ?? 'STC' }}</strong></span>
            </div>
            <p class="comms-body">{{ $post->body }}</p>
            <div class="users-form-actions">
                <a class="primary-action" href="{{ route('admin.communications.edit', $post) }}">Editar</a>
                @if ($post->type === 'plaque')
                    <a class="primary-action" href="{{ route('admin.communications.plaque', $post) }}" target="_blank" rel="noopener">Ver placa</a>
                @endif
            </div>
        </article>

        <aside class="users-permission-card">
            <h3>Publicación</h3>
            <form method="post" action="{{ route('admin.communications.status', $post) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="published">
                <button class="primary-action" type="submit">Publicar en app</button>
            </form>
            <form method="post" action="{{ route('admin.communications.status', $post) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="draft">
                <button type="submit">Volver a borrador</button>
            </form>
            <form method="post" action="{{ route('admin.communications.status', $post) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="archived">
                <button type="submit">Archivar</button>
            </form>
            <form method="post" action="{{ route('admin.communications.destroy', $post) }}" data-confirm="¿Eliminar {{ $post->title }}?">
                @csrf
                @method('DELETE')
                <button type="submit">Eliminar</button>
            </form>
        </aside>
    </section>
</x-layouts.stc>
