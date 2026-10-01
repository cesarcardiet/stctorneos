@php
    $months = [1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'];
    $when = $match->scheduled_at;
    $dateLabel = $when ? $when->format('d').' '.$months[(int) $when->format('n')] : 'Sin fecha';
    $timeLabel = $when?->format('H:i') ?: '—';
    $homeScore = $sheet?->home_score ?? $match->home_score;
    $awayScore = $sheet?->away_score ?? $match->away_score;
    $scoreText = ($homeScore === null && $awayScore === null) ? 'vs' : ((int) $homeScore).' - '.((int) $awayScore);
    $metaBits = array_filter([
        $tournament?->name,
        $category?->name,
        $category?->modalityLabel(),
        $match->stage ?: $match->zoneLabel(),
    ]);
    $slots = [
        5 => [[50, 86], [22, 62], [78, 62], [32, 32], [68, 32]],
        6 => [[50, 86], [22, 64], [78, 64], [22, 36], [78, 36], [50, 16]],
        7 => [[50, 88], [18, 66], [50, 68], [82, 66], [28, 38], [72, 38], [50, 16]],
        8 => [[50, 88], [18, 68], [50, 70], [82, 68], [22, 44], [78, 44], [32, 18], [68, 18]],
        9 => [[50, 88], [16, 70], [40, 72], [60, 72], [84, 70], [28, 44], [72, 44], [34, 18], [66, 18]],
        10 => [[50, 88], [16, 70], [38, 72], [62, 72], [84, 70], [22, 46], [50, 44], [78, 46], [32, 18], [68, 18]],
        11 => [[50, 90], [14, 72], [36, 74], [64, 74], [86, 72], [24, 48], [50, 50], [76, 48], [22, 20], [50, 14], [78, 20]],
    ];
    $shapeOf = function (array $points): string {
        if (count($points) < 2) {
            return '';
        }
        usort($points, fn ($a, $b) => $b[1] <=> $a[1]);
        $rows = [];
        foreach (array_slice($points, 1) as $point) {
            $band = (int) (round($point[1] / 14) * 14);
            $rows[$band] = ($rows[$band] ?? 0) + 1;
        }
        krsort($rows);

        return implode('-', $rows);
    };
    $packSide = function ($team) use ($match, $slots, $shapeOf) {
        $rows = $match->lineups->where('team_id', $team?->id);
        $starters = $rows->where('starter', true)->sortBy('sort_order')->values();
        $subs = $rows->where('starter', false)->sortBy('sort_order')->values();
        $count = $starters->count();
        $key = isset($slots[$count]) ? $count : 7;
        $plan = $slots[$key];
        $placed = [];
        $points = [];
        foreach ($starters as $index => $row) {
            $slot = $plan[$index] ?? [50, 50];
            $x = $row->pos_x ?? $slot[0];
            $y = $row->pos_y ?? $slot[1];
            $points[] = [(int) $x, (int) $y];
            $placed[] = [
                'x' => (int) $x,
                'y' => (int) $y,
                'number' => $row->shirt_number ?: ($row->player?->jersey_number ?: '·'),
                'name' => mb_strtoupper((string) ($row->player?->last_name ?: $row->player?->first_name ?: ''), 'UTF-8'),
            ];
        }

        return [
            'team' => $team,
            'placed' => $placed,
            'subs' => $subs,
            'shape' => $shapeOf($points),
            'staff' => collect($team?->staffMembers ?? []),
        ];
    };
    $sides = [$packSide($match->homeTeam), $packSide($match->awayTeam)];
    $scorers = collect($sheet?->events ?? [])
        ->where('type', 'goal')
        ->filter(fn ($event) => $event->player)
        ->groupBy('player_id')
        ->map(function ($events) {
            $event = $events->first();
            $count = $events->count();
            $minutes = $events->pluck('minute')->filter(fn ($minute) => $minute !== null)->map(fn ($minute) => $minute."'")->implode(' ');

            return [
                'name' => mb_strtoupper($event->player->fullName(), 'UTF-8'),
                'photo' => $event->player->photoUrl(),
                'shield' => $event->team?->shieldUrl(),
                'line' => $count.' '.($count === 1 ? 'GOL' : 'GOLES').($minutes !== '' ? ' · '.$minutes : ''),
            ];
        })
        ->values();
    $canOpenLineup = ($canOperateMatch && ! ($sheet?->locked)) || auth()->user()?->restrictsToAssignedClub();
@endphp

<article class="mf-poster">
    <header class="mf-head">
        <img class="mf-logo" src="{{ $tournament?->logoUrl() ?: asset('images/stc-logo.png') }}" alt="">
        <h2>Ficha del partido</h2>
        <p>{{ mb_strtoupper(implode(' · ', $metaBits), 'UTF-8') }}</p>
    </header>

    <div class="mf-score">
        <div class="mf-club">
            <img src="{{ $match->homeTeam?->shieldUrl() }}" alt="">
            <strong>{{ $match->homeTeam?->name ?: 'Local' }}</strong>
        </div>
        <div class="mf-result">
            <b data-ws-score-text>{{ $scoreText }}</b>
            <span class="is-{{ $match->statusTone() }}" data-ws-status-banner>{{ mb_strtoupper($match->statusLabel(), 'UTF-8') }}</span>
        </div>
        <div class="mf-club">
            <img src="{{ $match->awayTeam?->shieldUrl() }}" alt="">
            <strong>{{ $match->awayTeam?->name ?: 'Visitante' }}</strong>
        </div>
    </div>

    <ul class="mf-facts">
        <li>{{ $dateLabel }}</li>
        <li>{{ $timeLabel }}</li>
        <li data-ws-field-text>{{ $match->field?->name ?: 'Sin cancha' }}</li>
    </ul>

    <div class="mf-pitches">
        @foreach ($sides as $side)
            <section class="mf-pitch-card">
                <header>
                    <strong>{{ mb_strtoupper($side['team']?->name ?: 'Equipo', 'UTF-8') }}</strong>
                    @if ($side['shape'] !== '')
                        <small>{{ $side['shape'] }}</small>
                    @endif
                </header>
                <div class="mf-pitch">
                    <svg viewBox="0 0 68 105" preserveAspectRatio="none" aria-hidden="true">
                        <rect x="2" y="2" width="64" height="101" fill="none" stroke="#fff" stroke-width="0.7"/>
                        <line x1="2" y1="52.5" x2="66" y2="52.5" stroke="#fff" stroke-width="0.6"/>
                        <circle cx="34" cy="52.5" r="8" fill="none" stroke="#fff" stroke-width="0.6"/>
                        <rect x="14" y="2" width="40" height="16" fill="none" stroke="#fff" stroke-width="0.6"/>
                        <rect x="22" y="2" width="24" height="7" fill="none" stroke="#fff" stroke-width="0.6"/>
                        <rect x="14" y="87" width="40" height="16" fill="none" stroke="#fff" stroke-width="0.6"/>
                        <rect x="22" y="96" width="24" height="7" fill="none" stroke="#fff" stroke-width="0.6"/>
                    </svg>
                    @forelse ($side['placed'] as $token)
                        <span class="mf-token" style="left: {{ $token['x'] }}%; top: {{ $token['y'] }}%;">
                            <b>{{ $token['number'] }}</b>
                            <small>{{ $token['name'] }}</small>
                        </span>
                    @empty
                        <p class="mf-empty">Sin formación cargada</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <div class="mf-lower">
        <section class="mf-box">
            <h3>Suplentes</h3>
            <div class="mf-subs">
                @foreach ($sides as $side)
                    <ul>
                        @forelse ($side['subs'] as $row)
                            <li>
                                <b>{{ $row->shirt_number ?: ($row->player?->jersey_number ?: '—') }}</b>
                                <span>{{ mb_strtoupper($row->player?->last_name ?: $row->player?->fullName() ?: '', 'UTF-8') }}</span>
                            </li>
                        @empty
                            <li class="is-empty">Sin suplentes</li>
                        @endforelse
                    </ul>
                @endforeach
            </div>
        </section>
        <section class="mf-box">
            <h3>Cuerpo técnico</h3>
            <div class="mf-staff">
                @foreach ($sides as $side)
                    <div>
                        <img src="{{ $side['team']?->shieldUrl() }}" alt="">
                        @forelse ($side['staff']->take(2) as $member)
                            <p>
                                <small>{{ $member->role === 'director_tecnico' ? 'DT' : $member->roleLabel() }}</small>
                                {{ mb_strtoupper($member->fullName(), 'UTF-8') }}
                            </p>
                        @empty
                            <p class="is-empty">Sin cuerpo técnico</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    @if ($scorers->isNotEmpty())
        <section class="mf-scorers">
            <h3>Goleadores del partido</h3>
            <ul>
                @foreach ($scorers as $scorer)
                    <li>
                        <span class="mf-face">
                            <img src="{{ $scorer['photo'] }}" alt="">
                            @if ($scorer['shield'])
                                <img class="mf-badge" src="{{ $scorer['shield'] }}" alt="">
                            @endif
                        </span>
                        <strong>{{ $scorer['name'] }}</strong>
                        <small>{{ $scorer['line'] }}</small>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <footer class="mf-foot">
        @if ($canOpenLineup)
            <button type="button" data-ws-open="lineup">{{ ($canOperateMatch && ! ($sheet?->locked)) ? 'Editar alineación' : 'Ver listado' }}</button>
        @endif
        <span>Generado por STC Torneos</span>
    </footer>
</article>
