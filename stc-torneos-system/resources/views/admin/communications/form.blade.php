<x-layouts.stc
    :title="($post->exists ? 'Editar' : 'Crear').' contenido | STC Torneos'"
    active="Comunicaciones"
    heading="Crear / Editar contenido"
    subheading="Noticia, comunicado, contenido oficial o placa para la app."
>
    <a class="back-link" href="{{ route('admin.communications.index') }}">← Volver al listado</a>

    @if ($errors->any())
        <div class="system-alert">Revisá el contenido antes de guardar.</div>
    @endif

    <form class="ficha-review-panel admin-figma-form" method="post" action="{{ $url }}" enctype="multipart/form-data">
        @csrf
        @if ($method === 'PUT')
            @method('PUT')
        @endif

        <h3>Datos de publicación</h3>
        <div class="ficha-row">
            <label class="span-2">Título <input name="title" value="{{ old('title', $post->title) }}" placeholder="Fixture de Final Oro confirmado"></label>
            <label>Tipo
                <select name="type">
                    @foreach (\App\Models\ContentPost::typeLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $post->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Audiencia
                <select name="audience">
                    @foreach (\App\Models\ContentPost::audienceLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('audience', $post->audience) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="ficha-row">
            <label>Estado
                <select name="status">
                    @foreach (\App\Models\ContentPost::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $post->status ?: 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Torneo
                <select name="tournament_id">
                    <option value="">Todos</option>
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}" @selected((int) old('tournament_id', $post->tournament_id) === (int) $tournament->id)>{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Categoría
                <select name="category_id">
                    <option value="">Todas</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id', $post->category_id) === (int) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Programar <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $post->scheduled_at?->format('Y-m-d\TH:i')) }}"></label>
        </div>
        <div class="ficha-row">
            <label>Portada <input type="file" name="cover_file" accept="image/*"></label>
            <label class="span-3">Bajada <input name="summary" value="{{ old('summary', $post->summary) }}" placeholder="Resumen corto para la app"></label>
        </div>

        <h3>Cuerpo</h3>
        <div class="ficha-row">
            <label class="span-4"><textarea name="body" rows="8" placeholder="Texto oficial para publicar">{{ old('body', $post->body) }}</textarea></label>
        </div>
        <div class="ficha-doc-chips">
            <label class="ficha-doc-chip">
                <input type="checkbox" name="pinned" value="1" @checked(old('pinned', $post->pinned))>
                <span class="mark"></span>
                Fijar en home de la app
            </label>
        </div>

        <div class="admin-figma-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.communications.index') }}">CANCELAR</a>
            <button class="ficha-save" type="submit">Guardar contenido</button>
        </div>
    </form>
</x-layouts.stc>
