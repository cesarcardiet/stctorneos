<x-layouts.stc
    :title="$notification->title.' | Notificación'"
    active="Dashboard"
    heading="Notificación"
    :subheading="$notification->sent_at?->format('d/m/Y H:i') ?? ''"
>
    <a class="back-link" href="{{ route('dashboard') }}">← Volver al panel</a>

    <section class="stc-card" style="max-width:720px;margin-top:1rem;padding:1.25rem 1.4rem;">
        <p class="stc-eyebrow">Aviso · {{ $notification->audienceLabel() }}</p>
        <h2 style="margin:0.35rem 0 0.75rem;font-size:1.35rem;">{{ $notification->title }}</h2>
        <p style="white-space:pre-wrap;line-height:1.55;margin:0;">{{ $notification->body }}</p>
    </section>
</x-layouts.stc>
