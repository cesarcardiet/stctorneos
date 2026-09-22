<x-layouts.workspace
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <section class="ws-ficha">
        <article class="ws-card ws-player-public">
            <div class="ws-player-public-layout">
                <div class="ws-player-photo">
                    <img src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
                </div>
                <div>
                    <p class="stc-eyebrow">Jugador STC</p>
                    <h2 style="margin: .35rem 0 .75rem;">{{ $player->fullName() }}</h2>
                    <p class="ws-muted">
                        {{ $player->team?->name ?? 'Sin equipo' }}
                        @if ($player->team?->category)
                            · {{ $player->team->category->name }}
                        @endif
                    </p>
                    <p class="ws-muted">{{ $player->eligibilityLabel() }} · {{ $player->fileStatusLabel() }}</p>
                </div>
            </div>
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">Mis estadísticas</p>
            <section class="ws-kpis" style="margin-top: .75rem;">
                <article class="ws-kpi tone-ok">
                    <strong>{{ $stats['goals'] ?? 0 }}</strong>
                    <span>Goles</span>
                </article>
                <article class="ws-kpi tone-ok">
                    <strong>{{ $stats['assists'] ?? 0 }}</strong>
                    <span>Asistencias</span>
                </article>
                <article class="ws-kpi tone-warn">
                    <strong>{{ $stats['yellows'] ?? 0 }}</strong>
                    <span>Amarillas</span>
                </article>
                <article class="ws-kpi tone-warn">
                    <strong>{{ $stats['upcoming'] ?? 0 }}</strong>
                    <span>Próximos</span>
                </article>
            </section>
            <a class="ws-btn ghost" href="{{ route('workspace.player.stats') }}" style="margin-top: .75rem;">Ver historial completo</a>
        </article>

        @if (($recentEvents ?? collect())->isNotEmpty())
            <article class="ws-card">
                <p class="stc-eyebrow">Últimas jugadas</p>
                <ul class="ws-plays-list">
                    @foreach ($recentEvents as $event)
                        <li @class(['ws-play-card', 'is-'.$event->tone()])>
                            <x-ws-event-icon :type="$event->iconType()" />
                            <div class="ws-play-copy">
                                <strong>{{ $event->headline() }}</strong>
                                <span>{{ $event->minuteLabel() }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </article>
        @endif

        <article class="ws-card">
            <p class="stc-eyebrow">Torneos</p>
            <p class="ws-muted">Consultá clasificación, fixture y rankings de todos los torneos (solo lectura).</p>
            <a class="ws-btn ghost" href="{{ route('workspace.home') }}" style="margin-top: .75rem;">Ver torneos</a>
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">Accesos rápidos</p>
            <ul class="player-data-list">
                <li>
                    <span>Mis estadísticas</span>
                    <strong><a href="{{ route('workspace.player.stats') }}">Historial y desempeño</a></strong>
                </li>
                <li>
                    <span>Mis datos</span>
                    <strong><a href="{{ route('workspace.player.ficha') }}">Ver ficha completa</a></strong>
                </li>
                <li>
                    <span>Documentos</span>
                    <strong><a href="{{ route('workspace.player.documents') }}">Ver y descargar</a></strong>
                </li>
                    <strong><a href="{{ route('workspace.player.export') }}" target="_blank" rel="noopener">Imprimir / descargar</a></strong>
                </li>
                <li>
                    <span>Credencial</span>
                    <strong><a href="{{ route('workspace.player.credential') }}" target="_blank" rel="noopener">Carnet STC</a></strong>
                </li>
                @if ($player->team?->category)
                    <li>
                        <span>Clasificación</span>
                        <strong><a href="{{ route('workspace.categories.standings', $player->team->category) }}">Ver tablas</a></strong>
                    </li>
                @endif
            </ul>
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">Resumen</p>
            <ul class="player-data-list">
                <li><span>Documento</span><strong>{{ $player->formattedDocument() }}</strong></li>
                <li><span>Camiseta</span><strong>{{ $player->jersey_number ? 'N° '.$player->jersey_number : '—' }}</strong></li>
                <li><span>Posición</span><strong>{{ $player->position ?: '—' }}</strong></li>
                <li><span>Documentación</span><strong>{{ $player->documentationSummary() }}</strong></li>
                <li><span>Torneo</span><strong>{{ $player->team?->tournament?->name ?? '—' }}</strong></li>
                <li><span>Club</span><strong>{{ $player->team?->delegation?->name ?? '—' }}</strong></li>
            </ul>
        </article>
    </section>
</x-layouts.workspace>
