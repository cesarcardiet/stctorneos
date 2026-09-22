<x-layouts.workspace
    :title="$title"
    heading="Documentación"
    :subheading="$category->name"
    :category="$category"
    :tournament="$tournament"
    active="Configuración"
>
    <p class="ws-back"><a href="{{ route('workspace.categories.settings', $category) }}">← Configuración</a></p>

    <div data-live-filter>
        @include('workspace.partials.live-search', [
            'placeholder' => 'Buscar jugador, documento o equipo…',
            'empty' => 'No hay documentos con esa búsqueda.',
        ])
        <div class="ws-list">
            @forelse ($documents as $document)
                <div
                    class="ws-list-row"
                    data-live-item
                    data-search="{{ $document->player?->fullName() }} {{ $document->type }} {{ $document->player?->team?->name }} {{ $document->statusLabel() }}"
                >
                @if ($document->isImage() && $document->fileUrl())
                    <a class="ws-doc-thumb" href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">
                        <img src="{{ $document->fileUrl() }}" alt="{{ $document->type }}">
                    </a>
                @endif
                <div>
                    <strong>{{ $document->player?->fullName() }}</strong>
                    <span>{{ $document->type }} · {{ $document->player?->team?->name }} · {{ $document->statusLabel() }}</span>
                </div>
                <span class="ws-row-actions">
                    @if ($document->fileUrl())
                        <a href="{{ $document->fileUrl() }}" target="_blank" rel="noopener">Ver</a>
                    @endif
                    @if ($canEdit && ! \App\Models\PlayerDocument::requiresClubReviewForType($document->type))
                        <form method="post" action="{{ route('workspace.categories.documents.review', [$category, $document]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $document->status === 'approved' ? 'observed' : 'approved' }}">
                            <button type="submit" class="ws-btn ghost">{{ $document->status === 'approved' ? 'Observar' : 'Aprobar' }}</button>
                        </form>
                    @endif
                </span>
            </div>
        @empty
            <div class="ws-empty">No hay documentos en esta categoría.</div>
        @endforelse
        </div>
    </div>
</x-layouts.workspace>
