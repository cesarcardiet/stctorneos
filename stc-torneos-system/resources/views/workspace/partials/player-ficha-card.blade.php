@php
    use App\Support\Countries;

    $player = $player ?? null;
    $category = $category ?? $player?->team?->category;
    $tournament = $tournament ?? $category?->tournament ?? $player?->team?->tournament;
    $stats = $stats ?? ['matches_played' => 0, 'goals' => 0, 'assists' => 0, 'yellows' => 0];
    $showPrivate = $showPrivate ?? true;
    $canEdit = $canEdit ?? false;

    $countryCode = Countries::guessCode($player?->nationality, $player?->team?->delegation?->country_code);
    $flagUrl = Countries::flagUrl($countryCode);
    $nationalityLabel = strtoupper($player?->nationality ?: Countries::name($countryCode) ?: '—');

    $isVerified = in_array($player?->status, ['approved', 'enabled'], true);
    $categoryLabel = collect([
        $category?->birth_year,
        strtoupper($category?->name ?? ''),
    ])->filter()->implode(' ');

    $footLabel = match (strtolower((string) $player?->preferred_foot)) {
        'derecha', 'derecho' => 'Derecho',
        'izquierda', 'izquierdo' => 'Izquierdo',
        'ambidiestro' => 'Ambidiestro',
        default => $player?->preferred_foot ?: '—',
    };

    $heightLabel = $player?->height
        ? (str_contains((string) $player->height, 'm') ? $player->height : $player->height.' m')
        : '—';

    $weightLabel = $player?->weight
        ? (str_contains(strtolower((string) $player->weight), 'kg') ? $player->weight : $player->weight.' kg')
        : '—';
@endphp

<article class="pfc-card">
    <header class="pfc-hero">
        <div class="pfc-hero-backdrop" aria-hidden="true">
            <img src="{{ $player->photoUrl() }}" alt="">
        </div>

        <div class="pfc-hero-photo">
            <img src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
            @if ($canEdit && $category)
                <form class="ws-photo-change" method="post" action="{{ route('workspace.categories.players.documents', [$category, $player]) }}" enctype="multipart/form-data" data-ws-shield-crop data-ws-crop-kind="player" data-ws-shield-save="{{ route('workspace.categories.players.documents', [$category, $player]) }}">
                    @csrf
                    <input type="hidden" name="type" value="Foto del jugador">
                    <img data-ws-shield-preview src="{{ $player->photoUrl() }}" alt="" hidden>
                    <label>
                        Cambiar foto
                        <input type="file" name="file" accept="image/png,image/jpeg,image/webp">
                    </label>
                    <small>Se guarda al confirmar el recorte.</small>
                </form>
            @endif
        </div>

        <div class="pfc-hero-body">
            @if ($isVerified)
                <span class="pfc-verified">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.5l2.2 4.5 5 .7-3.6 3.5.9 5.1L10 13.2l-4.5 2.1.9-5.1-3.6-3.5 5-.7L10 1.5z" fill="currentColor"/></svg>
                    Perfil verificado
                </span>
            @endif

            <p class="pfc-first-name">{{ strtoupper($player->first_name) }}</p>
            <h2 class="pfc-last-name">{{ strtoupper($player->last_name) }}</h2>

            <p class="pfc-nationality">
                @if ($flagUrl)
                    <img src="{{ $flagUrl }}" alt="" loading="lazy">
                @endif
                <span>{{ $nationalityLabel }}</span>
            </p>

            <ul class="pfc-quick">
                <li>
                    <span class="pfc-quick-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z" fill="currentColor"/></svg>
                    </span>
                    <strong>{{ $player->age() ?? '—' }}</strong>
                    <small>Edad</small>
                </li>
                <li>
                    <span class="pfc-quick-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 2 3 7v2c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V7l-9-5Zm0 2.2 7 3.9v1.9c0 4.4-3 8.6-7 9.8-4-1.2-7-5.4-7-9.8V8.1l7-3.9Z" fill="currentColor"/></svg>
                    </span>
                    <strong>{{ $category?->birth_year ?? '—' }}</strong>
                    <small>Categoría</small>
                </li>
                <li>
                    <span class="pfc-quick-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M7 3h10v3H7V3Zm-1 5h12l-1 13H8L7 8Zm4 2v9h2v-9h-2Z" fill="currentColor"/></svg>
                    </span>
                    <strong>{{ $player->jersey_number ?? '—' }}</strong>
                    <small>Número</small>
                </li>
                <li>
                    <span class="pfc-quick-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 20c2-8 6-12 8-12s6 4 8 12H4Zm8-10a3 3 0 1 0-3 3 3 3 0 0 0 3-3Z" fill="currentColor"/></svg>
                    </span>
                    <strong>{{ $footLabel }}</strong>
                    <small>Pie hábil</small>
                </li>
            </ul>
        </div>
    </header>

    <section class="pfc-club-bar">
        <div class="pfc-club-item">
            <img src="{{ $player->team?->shieldUrl() }}" alt="">
            <div>
                <small>Club</small>
                <strong>{{ strtoupper($player->team?->name ?: 'Sin equipo') }}</strong>
            </div>
        </div>
        <div class="pfc-club-item">
            <span class="pfc-club-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 2 3 7v2c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V7l-9-5Z" fill="currentColor"/></svg>
            </span>
            <div>
                <small>Categoría</small>
                <strong>{{ $categoryLabel ?: '—' }}</strong>
            </div>
        </div>
        <div class="pfc-club-item pfc-club-position">
            <x-player-position-pitch :position="$player->position" size="sm" />
            <div>
                <small>Posición principal</small>
                <strong>{{ strtoupper($player->position ?: 'Sin definir') }}</strong>
            </div>
        </div>
        <div class="pfc-club-item pfc-club-position">
            <x-player-position-pitch size="sm" />
            <div>
                <small>Posición secundaria</small>
                <strong>Sin definir</strong>
            </div>
        </div>
    </section>

    <section class="pfc-physical">
        <div>
            <span class="pfc-physical-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 20V4h2v16H4Zm14 0V8h2v12h-2ZM11 20v-6h2v6h-2Zm-3.5-8L12 4l4.5 8h-9Z" fill="currentColor"/></svg>
            </span>
            <div>
                <small>Altura</small>
                <strong>{{ $heightLabel }}</strong>
            </div>
        </div>
        <div>
            <span class="pfc-physical-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3a5 5 0 0 1 5 5v1h2v14H5V9h2V8a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v1h6V8a3 3 0 0 0-3-3Z" fill="currentColor"/></svg>
            </span>
            <div>
                <small>Peso</small>
                <strong>{{ $weightLabel }}</strong>
            </div>
        </div>
        <div>
            <span class="pfc-physical-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 20c2-8 6-12 8-12s6 4 8 12H4Z" fill="currentColor"/></svg>
            </span>
            <div>
                <small>Pie hábil</small>
                <strong>{{ $footLabel }}</strong>
            </div>
        </div>
    </section>

    <div class="pfc-body-grid">
        <div class="pfc-left-stack">
            <section class="pfc-panel pfc-about">
                <header>
                    <span class="pfc-panel-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z" fill="currentColor"/></svg>
                    </span>
                    <h3>Sobre mí</h3>
                </header>
                <p>{{ filled($player->notes) ? $player->notes : 'Todavía no hay una presentación cargada para este jugador.' }}</p>
            </section>

            @if ($showPrivate)
                <section class="pfc-panel pfc-achievements">
                    <header>
                        <span class="pfc-panel-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M8 4h8l1 3h3v2h-1.1c.3 1.2.1 2.5-.7 3.5L16 18H8l-2.2-5.5c-.8-1-.9-2.3-.7-3.5H4V7h3l1-3Zm2.2 2-.4 1.2H14l-.4-1.2H10.2Z" fill="currentColor"/></svg>
                        </span>
                        <h3>Logros destacados</h3>
                    </header>
                    <p class="pfc-empty">Próximamente podrás ver distinciones y premios del jugador en torneos STC.</p>
                </section>
            @endif
        </div>

        <section class="pfc-panel pfc-stats">
            <header>
                <span class="pfc-panel-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 19V5h16v14H4Zm2-2h12V7H6v10Zm2-8h2v6H8V9Zm4 0h2v4h-2V9Zm4 0h2v8h-2V9Z" fill="currentColor"/></svg>
                </span>
                <h3>Mis estadísticas STC</h3>
            </header>
            <ul class="pfc-stats-grid">
                <li>
                    <span class="pfc-stat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M6 3h12v2H6V3Zm-1 4h14l-1 14H6L5 7Zm3 2v10h2V9H8Zm4 0v10h2V9h-2Z" fill="currentColor"/></svg>
                    </span>
                    <strong>{{ $stats['matches_played'] ?? 0 }}</strong>
                    <small>Partidos</small>
                </li>
                <li>
                    <span class="pfc-stat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="currentColor"/></svg>
                    </span>
                    <strong>{{ $stats['goals'] ?? 0 }}</strong>
                    <small>Goles</small>
                </li>
                <li>
                    <span class="pfc-stat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 12h16M12 4v16" stroke="currentColor" stroke-width="2"/></svg>
                    </span>
                    <strong>{{ $stats['assists'] ?? 0 }}</strong>
                    <small>Asistencias</small>
                </li>
                <li>
                    <span class="pfc-stat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M10 1.5 12.2 6l5 .7-3.6 3.5.9 5.1L10 13.2l-4.5 2.1.9-5.1L2.8 6.7l5-.7L10 1.5Z" fill="currentColor"/></svg>
                    </span>
                    <strong>{{ $stats['yellows'] ?? 0 }}</strong>
                    <small>Amarillas</small>
                </li>
            </ul>
            <p class="pfc-stats-foot">Datos acumulados en torneos STC</p>
        </section>
    </div>

    @if ($tournament)
        <section class="pfc-history">
            <header>
                <span class="pfc-panel-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M7 2h10v2h5v18H4V4h3V2Zm12 6H5v12h14V8ZM7 6V4h10v2H7Z" fill="currentColor"/></svg>
                </span>
                <h3>Historial en STC</h3>
            </header>
            <div class="pfc-history-body">
                <div class="pfc-history-count">
                    <strong>1</strong>
                    <span>Torneo jugado</span>
                </div>
                <div class="pfc-history-tournaments">
                    @if ($tournament->logoUrl())
                        <img src="{{ $tournament->logoUrl() }}" alt="{{ $tournament->name }}">
                    @endif
                    <span>{{ $tournament->name }}</span>
                </div>
                <div class="pfc-history-debut">
                    <span class="pfc-debut-star" aria-hidden="true">★</span>
                    <div>
                        <small>Debut en STC</small>
                        <strong>{{ $tournament->name }}</strong>
                    </div>
                </div>
            </div>
        </section>
    @endif
</article>
