<x-layouts.stc
    :title="$notification->title.' | STC Torneos'"
    active="Comunicaciones"
    heading="Detalle de notificación"
    :subheading="$notification->channelLabel().' · '.$notification->statusLabel()"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.communications.notifications') }}">← Volver a notificaciones</a>

    <section class="users-detail-layout">
        <article class="users-form-card">
            <h3>{{ $notification->title }}</h3>
            <p>{{ $notification->body }}</p>
            <div class="category-info-list">
                <span><small>Audiencia</small><strong>{{ $notification->audienceLabel() }}</strong></span>
                <span><small>Canal</small><strong>{{ $notification->channelLabel() }}</strong></span>
                <span><small>Destinatarios</small><strong>{{ $notification->recipients_count }}</strong></span>
                <span><small>Enviada</small><strong>{{ $notification->sent_at?->format('d/m/Y H:i') ?? '—' }}</strong></span>
            </div>

            @if (in_array($notification->status, ['draft', 'scheduled'], true))
                <form method="post" action="{{ route('admin.communications.notifications.send', $notification) }}" data-confirm="¿Enviar ahora a la audiencia seleccionada?">
                    @csrf
                    <button class="primary-action" type="submit">Enviar ahora</button>
                </form>
                <a class="users-edit-link" href="{{ route('admin.communications.notifications.edit', $notification) }}">Editar borrador</a>
                <form method="post" action="{{ route('admin.communications.notifications.destroy', $notification) }}" data-confirm="¿Eliminar este aviso?">
                    @csrf
                    @method('DELETE')
                    <button class="users-danger-btn" type="submit">Eliminar</button>
                </form>
            @endif

            <div class="delegation-figma-table comms-recipients-table">
                <div class="table-head">
                    <span>Destinatario</span>
                    <span>Rol</span>
                    <span>Estado</span>
                </div>
                @forelse ($notification->recipients as $recipient)
                    <div class="table-row">
                        <span><strong>{{ $recipient->user?->name ?? 'Público app' }}</strong></span>
                        <span>{{ $recipient->role_name ?: '—' }}</span>
                        <span>{{ $recipient->statusLabel() }}</span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>Todavía no hay destinatarios registrados.</strong></div>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.stc>
