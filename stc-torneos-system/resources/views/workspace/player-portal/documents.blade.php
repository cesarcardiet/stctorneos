<x-layouts.workspace
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <section class="ws-ficha">
        <article class="ws-card">
            <p class="stc-eyebrow">Constancia del tutor</p>
            <p class="ws-muted">Acá ves y descargás la constancia de autorización firmada por tu tutor.</p>
            @include('workspace.player-portal.partials.documents-list', ['player' => $player])
        </article>

        <div class="ws-form-actions">
            <a class="ws-btn ghost" href="{{ route('workspace.player.export') }}" target="_blank" rel="noopener">Descargar ficha completa</a>
            <a class="ws-btn" href="{{ route('workspace.player.credential') }}" target="_blank" rel="noopener">Credencial STC</a>
        </div>
    </section>
</x-layouts.workspace>
