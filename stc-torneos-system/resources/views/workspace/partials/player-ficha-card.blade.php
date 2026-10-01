@php
    use App\Support\Countries;

    $player = $player ?? null;
    $category = $category ?? $player?->team?->category;
    $tournament = $tournament ?? $category?->tournament ?? $player?->team?->tournament;
    $stats = $stats ?? ['matches_played' => 0, 'goals' => 0, 'assists' => 0, 'yellows' => 0, 'reds' => 0];
    $canEdit = $canEdit ?? false;

    $countryCode = Countries::guessCode($player?->nationality, $player?->team?->delegation?->country_code);
    $flagUrl = Countries::flagUrl($countryCode);
    $nationalityLabel = strtoupper($player?->nationality ?: Countries::name($countryCode) ?: '—');

    $footLabel = match (strtolower((string) $player?->preferred_foot)) {
        'derecha', 'derecho' => 'Derecha',
        'izquierda', 'izquierdo' => 'Izquierda',
        'ambidiestro' => 'Ambidiestro',
        default => $player?->preferred_foot ?: '—',
    };

    $jersey = $player?->jersey_number ?: '—';
    $birthLabel = $player?->birth_date?->format('d/m/Y') ?: '—';
    $ageLabel = $player?->age() !== null ? $player->age().' años' : null;
    $tournamentName = strtoupper($tournament?->name ?: 'STC TORNEOS');
    $categoryYear = $category?->birth_year;
    $categoryName = strtoupper($category?->name ?: '');
    $logoUrl = $tournament?->logoUrl();
    $fileName = \Illuminate\Support\Str::slug($player?->fullName() ?: 'jugador');
@endphp

<article class="stc-pcard" data-pcard data-pcard-name="{{ $fileName }}">
    <div class="stc-pcard-poster">
        <img class="stc-pcard-photo" src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
        <div class="stc-pcard-shade" aria-hidden="true"></div>

        <div class="stc-pcard-mark" aria-hidden="true">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="">
            @else
                <svg viewBox="0 0 120 120" fill="none">
                    <circle cx="60" cy="28" r="16" stroke="currentColor" stroke-width="3"/>
                    <path d="M48 28h24M60 12v32M50 20c8 4 12 12 10 20M70 20c-8 4-12 12-10 20" stroke="currentColor" stroke-width="2"/>
                    <text x="60" y="86" text-anchor="middle" fill="currentColor" font-size="28" font-weight="800">STC</text>
                </svg>
            @endif
            <span>{{ $tournamentName }}</span>
        </div>

        <header class="stc-pcard-top">
            <p>{{ $tournamentName }}</p>
            <div>
                @if ($categoryYear)
                    <strong>CAT. {{ $categoryYear }}</strong>
                @endif
                @if ($categoryName)
                    <span>{{ $categoryName }}</span>
                @endif
            </div>
        </header>

        <div class="stc-pcard-copy">
            <p class="stc-pcard-number">{{ $jersey }}</p>
            <p class="stc-pcard-first">{{ mb_strtoupper($player->first_name, 'UTF-8') }}</p>
            <h2 class="stc-pcard-last">{{ mb_strtoupper($player->last_name, 'UTF-8') }}</h2>

            <ul class="stc-pcard-facts">
                <li>
                    <span class="stc-pcard-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3" stroke="currentColor" stroke-width="1.8"/></svg>
                    </span>
                    <div><strong>{{ $player->position ?: 'Sin definir' }}</strong></div>
                </li>
                <li>
                    <span class="stc-pcard-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4M16 3v4M4 10h16" stroke="currentColor" stroke-width="1.8"/></svg>
                    </span>
                    <div>
                        <strong>{{ $birthLabel }}</strong>
                        @if ($ageLabel)
                            <small>{{ $ageLabel }}</small>
                        @endif
                    </div>
                </li>
                <li>
                    <span class="stc-pcard-ico" aria-hidden="true">
                        @if ($flagUrl)
                            <img src="{{ $flagUrl }}" alt="">
                        @else
                            <svg viewBox="0 0 24 24"><path d="M5 4h10l-2 4 2 4H5V4Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 4v16" stroke="currentColor" stroke-width="1.8"/></svg>
                        @endif
                    </span>
                    <div>
                        <strong>{{ $nationalityLabel }}</strong>
                        <small>Nacionalidad</small>
                    </div>
                </li>
                <li>
                    <span class="stc-pcard-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M8 4h8l1.2 4H6.8L8 4Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 8h10l-1 12H8L7 8Z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                    </span>
                    <div>
                        <strong>{{ $jersey }}</strong>
                        <small>Número</small>
                    </div>
                </li>
                <li>
                    <span class="stc-pcard-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M5 16c3-8 6-10 7-10s4 2 7 10H5Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 16c.6-3 1.8-5 3-5s2.4 2 3 5" stroke="currentColor" stroke-width="1.6"/></svg>
                    </span>
                    <div>
                        <strong>{{ $footLabel }}</strong>
                        <small>Pierna hábil</small>
                    </div>
                </li>
            </ul>
        </div>

        @if ($player?->team)
            <div class="stc-pcard-club">
                <img class="stc-pcard-club-shield" src="{{ $player->team->shieldUrl() }}" alt="{{ $player->team->name }}">
                <small>{{ $player->team->name }}</small>
            </div>
        @endif

        @if ($canEdit && $category)
            <form class="ws-photo-change stc-pcard-photo-edit" method="post" action="{{ route('workspace.categories.players.documents', [$category, $player]) }}" enctype="multipart/form-data" data-ws-shield-crop data-ws-crop-kind="player" data-ws-shield-save="{{ route('workspace.categories.players.documents', [$category, $player]) }}">
                @csrf
                <input type="hidden" name="type" value="Foto del jugador">
                <img data-ws-shield-preview src="{{ $player->photoUrl() }}" alt="" hidden>
                <label>
                    Cambiar foto
                    <input type="file" name="file" accept="image/png,image/jpeg,image/webp">
                </label>
            </form>
        @endif
    </div>

    <section class="stc-pcard-stats">
        <h3>Estadísticas del jugador</h3>
        <ul>
            <li>
                <span class="stc-pcard-stat-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M12 5v14M3 12h18" stroke="currentColor" stroke-width="1.4"/></svg>
                </span>
                <strong>{{ $stats['matches_played'] ?? 0 }}</strong>
                <small>Partidos</small>
            </li>
            <li>
                <span class="stc-pcard-stat-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M12 4c2 3 2 13 0 16M12 4c-2 3-2 13 0 16M4.5 9h15M4.5 15h15" stroke="currentColor" stroke-width="1.3"/></svg>
                </span>
                <strong>{{ $stats['goals'] ?? 0 }}</strong>
                <small>Goles</small>
            </li>
            <li>
                <span class="stc-pcard-stat-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M5 16c3-8 6-10 7-10s4 2 7 10H5Z" fill="none" stroke="currentColor" stroke-width="1.7"/></svg>
                </span>
                <strong>{{ $stats['assists'] ?? 0 }}</strong>
                <small>Asistencias</small>
            </li>
            <li>
                <span class="stc-pcard-stat-ico is-cards" aria-hidden="true">
                    <span class="is-yellow"></span>
                    <span class="is-red"></span>
                </span>
                <strong>{{ ($stats['yellows'] ?? 0) }}/{{ ($stats['reds'] ?? 0) }}</strong>
                <small>Amarillas / Rojas</small>
            </li>
        </ul>
    </section>

    <button type="button" class="stc-pcard-download" data-pcard-download>Descargar imagen</button>
</article>
