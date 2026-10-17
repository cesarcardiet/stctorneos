@php
    $tournament = $notification->tournament;
    $post = $notification->post;
    $category = $post?->category;
    $author = $notification->author;
@endphp

<x-layouts.workspace
    :title="$notification->title.' | Notificación'"
    heading="Notificación"
    :subheading="$notification->sent_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? ''"
    active="Torneos"
    :tournament="$tournament"
>
    <a class="back-link" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('workspace.home') }}">← Volver</a>

    <article class="ws-inbox-detail">
        <header class="ws-inbox-detail__head">
            <p class="ws-inbox-detail__eyebrow">Aviso · {{ $notification->audienceLabel() }} · {{ $notification->channelLabel() }}</p>
            <h2>{{ $notification->title }}</h2>
            @if ($notification->body)
                <p class="ws-inbox-detail__body">{{ $notification->body }}</p>
            @endif
        </header>

        <section class="ws-inbox-detail__meta" aria-label="Contexto del aviso">
            <div>
                <span>Torneo</span>
                <strong>{{ $tournament?->name ?? 'Sin torneo asignado' }}</strong>
                @if ($tournament?->edition)
                    <em>{{ $tournament->edition }}</em>
                @endif
            </div>
            <div>
                <span>Categoría</span>
                <strong>{{ $category?->name ?? ($post ? 'Publicación general' : 'No especificada') }}</strong>
            </div>
            <div>
                <span>Enviado por</span>
                <strong>{{ $author?->name ?? 'Sistema STC' }}</strong>
                @if ($author?->roleLabel())
                    <em>{{ $author->roleLabel() }}</em>
                @endif
            </div>
            <div>
                <span>Fecha de envío</span>
                <strong>{{ $notification->sent_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</strong>
            </div>
        </section>

        @if ($post)
            <section class="ws-inbox-detail__related">
                <h3>Contenido relacionado</h3>
                <p>{{ $post->title }}</p>
                @if ($post->summary)
                    <p class="ws-inbox-detail__muted">{{ $post->summary }}</p>
                @endif
                @if (auth()->user()?->hasPermission('communications.manage'))
                    <a class="ws-btn ghost" href="{{ route('admin.communications.show', $post) }}">Ver publicación en admin</a>
                @endif
            </section>
        @endif

        <footer class="ws-inbox-detail__actions">
            @if ($tournament)
                <a class="ws-btn" href="{{ route('workspace.tournaments.show', $tournament) }}">Ir al torneo</a>
            @endif
            @if ($category)
                <a class="ws-btn ghost" href="{{ route('workspace.categories.home', $category) }}">Ir a la categoría</a>
            @endif
            @if (auth()->user()?->hasPermission('communications.manage'))
                <a class="ws-btn ghost" href="{{ route('admin.communications.notifications.show', $notification) }}">Abrir en comunicaciones</a>
            @endif
        </footer>
    </article>
</x-layouts.workspace>
