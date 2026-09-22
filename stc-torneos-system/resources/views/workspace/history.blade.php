<x-layouts.workspace
    :title="$title"
    heading="Historial de cambios"
    :subheading="$category->name"
    :category="$category"
    :tournament="$tournament"
    active="Configuración"
>
    <p class="ws-back"><a href="{{ route('workspace.categories.settings', $category) }}">← Configuración</a></p>

    <ul class="ws-people">
        @forelse ($logs as $log)
            <li>
                <div>
                    <strong>{{ $log->description }}</strong>
                    <span>{{ $log->user?->name }} · {{ $log->created_at?->format('d/m H:i') }}</span>
                </div>
            </li>
        @empty
            <li class="ws-muted">Todavía no hay movimientos en Operación.</li>
        @endforelse
    </ul>
</x-layouts.workspace>
