<x-layouts.workspace
    :title="$title"
    heading="Ficha técnica"
    :subheading="$player->team?->name.' · '.$category->name"
    :category="$category"
    :tournament="$tournament"
    active="Jugadores"
>
    <p class="ws-back">
        <a href="{{ route('workspace.categories.players', [$category, 'team_id' => $player->team_id]) }}">← Jugadores</a>
        @if ($player->team)
            <a href="{{ route('workspace.categories.teams.show', [$category, $player->team]) }}">Equipo</a>
        @endif
        @if ($canEdit ?? false)
            <a class="stc-pill-btn ws-player-edit-inline" href="{{ route('workspace.categories.players.edit', [$category, $player]) }}">Editar jugador</a>
        @endif
    </p>

    <section class="ws-ficha ws-ficha-premium">
        @include('workspace.partials.player-ficha-card', [
            'player' => $player,
            'category' => $category,
            'tournament' => $tournament,
            'stats' => $stats ?? [],
            'showPrivate' => $canViewFullFicha ?? false,
            'canEdit' => $canEdit ?? false,
        ])

        @if ($canEdit ?? false)
            <x-ws-guardian-invite :player="$player" :category="$category" :can-edit="true" />
        @endif

        @if ($canViewFullFicha ?? false)
            <details class="ws-ficha-admin">
                <summary>Datos administrativos y documentación</summary>

                @include('workspace.partials.player-ficha-readonly', ['player' => $player, 'category' => $category])

                <article class="ws-card">
                    <p class="stc-eyebrow">Documentación</p>
                    <p class="ws-muted">Archivos cargados en la ficha. Para subir o modificar documentos, usá Editar ficha.</p>
                    <ul class="ws-doc-list">
                        @foreach (\App\Models\Player::documentTypes() as $type)
                            @include('workspace.partials.player-doc-row', [
                                'player' => $player,
                                'category' => $category,
                                'type' => $type,
                                'label' => $type,
                                'document' => $player->documentByType($type),
                                'allowUpload' => false,
                                'allowReview' => false,
                                'canEdit' => false,
                            ])
                        @endforeach
                    </ul>
                </article>

                @if ($canEdit ?? false)
                    <p class="ws-form-actions">
                        <a class="ws-btn" href="{{ route('workspace.categories.players.edit', [$category, $player]) }}">Editar ficha</a>
                    </p>
                @endif
            </details>
        @endif
    </section>
</x-layouts.workspace>
