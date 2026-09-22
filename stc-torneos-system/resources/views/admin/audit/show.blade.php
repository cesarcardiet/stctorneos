<x-layouts.stc
    title="Detalle Registro de Auditoría | STC Torneos"
    active="Auditoría"
    heading="Detalle Registro de Auditoría"
    subheading="Registro inmutable según doc 3.20 · no eliminable por operación administrativa."
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.audit.index') }}">← Volver al listado</a>

    <section class="audit-detail-layout">
        <article class="audit-detail-card">
            <h2>{{ $log->description ?: $log->actionLabel() }}</h2>
            <p class="audit-detail-code">
                {{ $log->referenceCode() }}
                · {{ $log->created_at?->format('d/m/Y H:i') }} hs
                · {{ $log->relevanceLabel() }}
                @if ($log->isReviewed())
                    · Revisado
                @endif
            </p>

            <div class="audit-detail-fields">
                <div class="fields-form-field">
                    <span>Usuario</span>
                    <strong>{{ $log->user?->name ?? 'Sistema' }}@if ($log->user?->email) · {{ $log->user->email }}@endif</strong>
                </div>
                <div class="fields-form-field">
                    <span>Rol</span>
                    <strong>{{ $log->user?->roleLabel() ?? 'Sin rol' }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>Registro afectado</span>
                    <strong>{{ $log->recordLabel() }} · {{ $log->module ?: 'Sistema' }}</strong>
                </div>
                <div class="fields-form-field is-old">
                    <span>Valor anterior</span>
                    <strong>{{ $log->previousValue() }}</strong>
                </div>
                <div class="fields-form-field is-new">
                    <span>Valor nuevo</span>
                    <strong>{{ $log->newValue() }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>Motivo</span>
                    <strong>{{ $log->reason() }}</strong>
                </div>
                <div class="fields-form-field">
                    <span>IP / agente</span>
                    <strong>{{ $log->ip_address ?: '—' }} · {{ $log->user_agent ?: '—' }}</strong>
                </div>
            </div>

            <div class="audit-detail-actions">
                <a class="users-edit-link" href="{{ route('admin.audit.download', $log) }}">Descargar log</a>
                @if ($log->isReviewed())
                    <span class="users-edit-link audit-reviewed-pill">Revisado</span>
                @else
                    <form method="post" action="{{ route('admin.audit.review', $log) }}">
                        @csrf
                        @method('PATCH')
                        <button class="users-edit-link audit-review-btn" type="submit">Marcar revisado</button>
                    </form>
                @endif
            </div>
        </article>

        <aside class="audit-timeline-card">
            <h3>Línea de tiempo</h3>
            <ol class="audit-timeline">
                @forelse ($timeline as $event)
                    <li @class(['is-current' => $event->id === $log->id])>
                        <time>{{ $event->created_at?->format('H:i') }}</time>
                        <span>{{ $event->actionLabel() }}</span>
                    </li>
                @empty
                    <li class="is-current">
                        <time>{{ $log->created_at?->format('H:i') }}</time>
                        <span>{{ $log->actionLabel() }}</span>
                    </li>
                @endforelse
            </ol>
        </aside>
    </section>
</x-layouts.stc>
