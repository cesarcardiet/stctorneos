<x-layouts.workspace
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <section class="ws-ficha ws-ficha-premium">
        @include('workspace.partials.player-ficha-card', [
            'player' => $player,
            'category' => $category,
            'tournament' => $tournament,
            'stats' => $stats ?? [],
            'showPrivate' => true,
        ])

        <details class="ws-ficha-admin" open>
            <summary>Datos completos de la ficha</summary>

            <article class="ws-card">
                <p class="stc-eyebrow">Estado de la ficha</p>
                <ul class="player-data-list">
                    <li><span>Estado ficha</span><strong>{{ $player->fileStatusLabel() }}</strong></li>
                    <li><span>Revisión</span><strong>{{ $player->reviewStatusLabel() }}</strong></li>
                    <li><span>Documentación</span><strong>{{ $player->documentationSummary() }}</strong></li>
                    <li><span>Autorizaciones</span><strong>{{ $player->authorizationSummary() }}</strong></li>
                    <li><span>Habilitación</span><strong>{{ $player->eligibilityLabel() }}</strong></li>
                    @if ($player->observation_reason)
                        <li><span>Observación</span><strong>{{ $player->observation_reason }}</strong></li>
                    @endif
                </ul>
            </article>

            @include('workspace.partials.player-ficha-readonly', ['player' => $player, 'category' => $category])

            <article class="ws-card">
                <p class="stc-eyebrow">Constancia del tutor</p>
                @include('workspace.player-portal.partials.documents-list', ['player' => $player])
            </article>

            <div class="ws-form-actions">
                <a class="ws-btn ghost" href="{{ route('workspace.player.export') }}" target="_blank" rel="noopener">Descargar ficha completa</a>
                <a class="ws-btn" href="{{ route('workspace.player.credential') }}" target="_blank" rel="noopener">Credencial STC</a>
            </div>
        </details>
    </section>
</x-layouts.workspace>
