<x-layouts.stc
    title="Notificaciones | STC Torneos"
    active="Comunicaciones"
    heading="Notificaciones"
    subheading="Avisos push y en app para delegados, árbitros, staff o público."
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <section class="admin-figma-kpis">
        @foreach ($stats as [$value, $label, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    @include('admin.communications.partials.tabs')

    <div class="results-toolbar">
        <p>El envío registra destinatarios reales según audiencia. El público queda listo para la app.</p>
        <a href="{{ route('admin.communications.notifications.create') }}">Nueva notificación</a>
    </div>

    <section class="sheets-list-card admin-figma-panel">
        <h3>Avisos</h3>
        <div class="delegation-figma-table comms-figma-table admin-figma-table">
            <div class="table-head">
                <span>Título</span>
                <span>Canal</span>
                <span>Audiencia</span>
                <span>Estado</span>
                <span>Acción</span>
            </div>
            @forelse ($items as $item)
                <div class="table-row">
                    <span>
                        <strong>{{ $item->title }}</strong>
                        <small>{{ $item->recipients_count }} dest. · {{ $item->author?->name ?? 'STC' }}</small>
                    </span>
                    <span>{{ $item->channelLabel() }}</span>
                    <span>{{ $item->audienceLabel() }}</span>
                    <span>{{ $item->statusLabel() }}</span>
                    <span class="category-actions">
                        <a href="{{ route('admin.communications.notifications.show', $item) }}">Ver</a>
                        @if (in_array($item->status, ['draft', 'scheduled'], true))
                            <form method="post" action="{{ route('admin.communications.notifications.send', $item) }}">
                                @csrf
                                <button type="submit">Enviar</button>
                            </form>
                        @endif
                    </span>
                </div>
            @empty
                <div class="category-empty-state"><strong>No hay notificaciones cargadas.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
