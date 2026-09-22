<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class FixtureMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'tournament_id',
        'category_id',
        'field_id',
        'home_team_id',
        'away_team_id',
        'scheduled_at',
        'stage',
        'round',
        'status',
        'home_score',
        'away_score',
        'minute',
        'duration_minutes',
        'referee_name',
        'referee_user_id',
        'assistant_user_id',
        'scorer_user_id',
        'period',
        'published',
        'published_at',
        'notes',
        'next_match_id',
        'next_slot',
        'reopen_reason',
        'previous_result',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'published' => 'boolean',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'previous_result' => 'array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'scheduled' => 'Programado',
            'live' => 'En juego',
            'paused' => 'Pausado',
            'finished' => 'Finalizado',
            'suspended' => 'Suspendido',
            'rescheduled' => 'Reprogramado',
            'reopened' => 'Reabierto',
            'validated' => 'Finalizado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function operatorStatusLabels(): array
    {
        return [
            'scheduled' => 'Programado',
            'live' => 'En juego',
            'paused' => 'Pausado',
            'finished' => 'Finalizado',
            'suspended' => 'Suspendido',
            'rescheduled' => 'Reprogramado',
            'reopened' => 'Reabierto',
        ];
    }

    public function statusLabel(): string
    {
        if ($this->status === 'validated') {
            return 'Finalizado';
        }

        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function statusTone(): string
    {
        return match ($this->statusOption()) {
            'live' => 'live',
            'finished' => 'done',
            'paused', 'suspended', 'reopened' => 'warn',
            default => 'soon',
        };
    }

    public function statusOption(): string
    {
        return $this->status === 'validated' ? 'finished' : (string) $this->status;
    }

    public function countsForStandings(): bool
    {
        if (in_array($this->status, ['suspended', 'paused', 'reopened'], true)) {
            return false;
        }

        if (in_array($this->status, ['finished', 'validated'], true)) {
            return true;
        }

        [$home, $away] = $this->officialScores();
        if ($home === null || $away === null) {
            return false;
        }

        if ($this->status === 'live') {
            return true;
        }

        return ((int) $home + (int) $away) > 0;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    public function officialScores(): array
    {
        $home = $this->home_score;
        $away = $this->away_score;

        if ($home === null && $away === null && $this->sheet) {
            $home = $this->sheet->home_score;
            $away = $this->sheet->away_score;
        }

        if ($home === null || $away === null) {
            return [null, null];
        }

        return [(int) $home, (int) $away];
    }

    public function agendaDateLabel(): string
    {
        if (! $this->scheduled_at) {
            return 'Sin horario';
        }

        $days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $months = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];

        return $days[$this->scheduled_at->dayOfWeek].' '.$this->scheduled_at->format('d').' '.$months[(int) $this->scheduled_at->month].' · '.$this->scheduled_at->format('H:i');
    }

    public function agendaLine(): string
    {
        return trim(($this->category?->name ?? 'Categoría').' · '.$this->title());
    }

    public function agendaStatusLabel(): string
    {
        if ($this->status === 'scheduled' && $this->published) {
            return 'Confirmado';
        }

        if ($this->status === 'rescheduled' || ($this->status === 'scheduled' && ! $this->published)) {
            return $this->status === 'rescheduled' ? 'Pendiente' : 'Programado';
        }

        return $this->statusLabel();
    }

    public function agendaStatusTone(): string
    {
        return in_array($this->agendaStatusLabel(), ['Confirmado', 'En juego', 'Finalizado'], true)
            ? 'ok'
            : 'warn';
    }

    public function actionLabel(): string
    {
        return match ($this->status) {
            'live', 'paused', 'reopened' => 'Operar',
            'scheduled', 'rescheduled' => 'Editar',
            default => 'Ver',
        };
    }

    public function title(): string
    {
        return trim(($this->homeTeam?->name ?? 'Local').' vs '.($this->awayTeam?->name ?? 'Visitante'));
    }

    public function listingSortKey(): string
    {
        $round = \App\Support\CategoryWorkspace::normalizeRound($this->round, $this->stage);
        $zone = $this->zoneLabel();
        $names = [
            trim((string) ($this->homeTeam?->name ?? '')),
            trim((string) ($this->awayTeam?->name ?? '')),
        ];
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $round.'|'.$zone.'|'.$names[0].'|'.$names[1];
    }

    public function groupLetter(?Team $team): ?string
    {
        $group = strtoupper(trim((string) $team?->group_name));

        return $group !== '' ? $group : null;
    }

    public function teamGroupLabel(?Team $team): string
    {
        $letter = $this->groupLetter($team);
        if (! $letter) {
            return '';
        }

        $this->loadMissing('category');

        return $this->category?->groupDisplayName($letter) ?: 'Grupo '.$letter;
    }

    public function isKnockoutStage(): bool
    {
        return (bool) preg_match('/semi|final|cuartos|octavos|playoff|llave/i', trim((string) $this->stage.' '.(string) $this->round));
    }

    public function isInterzonal(): bool
    {
        $home = $this->groupLetter($this->homeTeam);
        $away = $this->groupLetter($this->awayTeam);

        return $home !== null && $away !== null && $home !== $away;
    }

    public function zoneLabel(): string
    {
        if ($this->isKnockoutStage()) {
            $stage = trim((string) $this->stage);
            $round = trim((string) $this->round);
            if ($stage !== '' && preg_match('/semi|final|cuartos|octavos|playoff|llave|oro|plata|bronce/i', $stage)) {
                return $stage;
            }
            if ($round !== '' && preg_match('/semi|final|cuartos|octavos|playoff|llave|oro|plata|bronce/i', $round)) {
                return $round;
            }

            return $stage !== '' ? $stage : $round;
        }

        $home = $this->groupLetter($this->homeTeam);
        $away = $this->groupLetter($this->awayTeam);

        if ($home && $away && $home !== $away) {
            $this->loadMissing('category');
            $letters = collect([$home, $away])->sort()->values();
            $left = $this->category?->groupDisplayName($letters[0]) ?: 'Grupo '.$letters[0];
            $right = $this->category?->groupDisplayName($letters[1]) ?: 'Grupo '.$letters[1];

            return 'Interzonal · '.$left.' vs '.$right;
        }

        if ($home && $home === $away) {
            $this->loadMissing('category');

            return $this->category?->groupDisplayName($home) ?: 'Grupo '.$home;
        }

        if ($home || $away) {
            $letter = $home ?: $away;
            $this->loadMissing('category');

            return $this->category?->groupDisplayName($letter) ?: 'Grupo '.$letter;
        }

        return '';
    }

    public function scoreLine(): string
    {
        if ($this->home_score === null && $this->away_score === null) {
            return 'vs';
        }

        $line = ($this->home_score ?? 0).' - '.($this->away_score ?? 0);

        if ($this->relationLoaded('penaltyKicks') || $this->period === 'pen') {
            $pen = $this->penaltyScore();
            if (($pen['home'] + $pen['away']) > 0) {
                $line .= ' ('.$pen['home'].'-'.$pen['away'].' pen)';
            }
        }

        return $line;
    }

    public function winnerTeamId(): ?int
    {
        [$home, $away] = $this->officialScores();
        if ($home === null || $away === null) {
            return null;
        }

        if ($home > $away) {
            return $this->home_team_id;
        }

        if ($away > $home) {
            return $this->away_team_id;
        }

        if ($this->isKnockoutStage()) {
            return $this->penaltyWinnerId();
        }

        return null;
    }

    public function winnerTeam(): ?Team
    {
        $id = $this->winnerTeamId();
        if (! $id) {
            return null;
        }

        if ((int) $id === (int) $this->home_team_id) {
            return $this->homeTeam;
        }

        if ((int) $id === (int) $this->away_team_id) {
            return $this->awayTeam;
        }

        return Team::query()->find($id);
    }

    public function propagateWinner(): void
    {
        if (! $this->next_match_id || ! in_array($this->next_slot, ['home', 'away'], true)) {
            return;
        }

        $winnerId = $this->winnerTeamId();
        if (! $winnerId) {
            return;
        }

        $next = $this->nextMatch ?: self::query()->find($this->next_match_id);
        if (! $next) {
            return;
        }

        $column = $this->next_slot === 'away' ? 'away_team_id' : 'home_team_id';
        $next->update([$column => $winnerId]);
    }

    public function durationMinutes(): int
    {
        if ($this->duration_minutes) {
            return (int) $this->duration_minutes;
        }

        $periods = max(1, (int) ($this->category?->periods ?: 2));
        $minutes = max(1, (int) ($this->category?->period_duration ?: 35));

        return $periods * $minutes;
    }

    public function overlaps(self $other, int $gapMinutes = 15): bool
    {
        if (! $this->scheduled_at || ! $other->scheduled_at) {
            return false;
        }

        $start = $this->scheduled_at;
        $end = $start->copy()->addMinutes($this->durationMinutes() + $gapMinutes);
        $otherStart = $other->scheduled_at;
        $otherEnd = $otherStart->copy()->addMinutes($other->durationMinutes() + $gapMinutes);

        return $start->lt($otherEnd) && $otherStart->lt($end);
    }

    /**
     * @param  Collection<int, self>  $matches
     * @return Collection<int, array{match: self, other: self, type: string, label: string}>
     */
    public static function detectConflicts(Collection $matches, int $gapMinutes = 15): Collection
    {
        $conflicts = collect();

        foreach ($matches as $match) {
            foreach ($matches as $other) {
                if ($match->id >= $other->id || ! $match->overlaps($other, $gapMinutes)) {
                    continue;
                }

                if ($match->field_id && $match->field_id === $other->field_id) {
                    $conflicts->push([
                        'match' => $match,
                        'other' => $other,
                        'type' => 'field',
                        'label' => ($match->field?->name ?? 'Cancha').' superpuesta',
                    ]);
                }

                $teamIds = array_filter([
                    $match->home_team_id,
                    $match->away_team_id,
                    $other->home_team_id,
                    $other->away_team_id,
                ]);

                if (count($teamIds) !== count(array_unique($teamIds))) {
                    $conflicts->push([
                        'match' => $match,
                        'other' => $other,
                        'type' => 'team',
                        'label' => 'Equipo repetido en el mismo horario',
                    ]);
                }

                $delegations = array_filter([
                    $match->homeTeam?->delegation_id,
                    $match->awayTeam?->delegation_id,
                    $other->homeTeam?->delegation_id,
                    $other->awayTeam?->delegation_id,
                ]);

                if (count($delegations) !== count(array_unique($delegations))) {
                    $conflicts->push([
                        'match' => $match,
                        'other' => $other,
                        'type' => 'delegation',
                        'label' => 'Delegación repetida',
                    ]);
                }

                $sameReferee = ($match->referee_user_id && $match->referee_user_id === $other->referee_user_id)
                    || ($match->referee_name && $other->referee_name && $match->referee_name === $other->referee_name);
                $sameAssistant = $match->assistant_user_id && $match->assistant_user_id === $other->assistant_user_id;

                if ($sameReferee) {
                    $conflicts->push([
                        'match' => $match,
                        'other' => $other,
                        'type' => 'referee',
                        'label' => 'Árbitro ocupado',
                    ]);
                }

                if ($sameAssistant) {
                    $conflicts->push([
                        'match' => $match,
                        'other' => $other,
                        'type' => 'assistant',
                        'label' => 'Asistente ocupado',
                    ]);
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param  Collection<int, array{match: self, other: self, type: string, label: string}>  $conflicts
     */
    public function isInConflict(Collection $conflicts): bool
    {
        return $conflicts->contains(fn (array $conflict) => $conflict['match']->id === $this->id || $conflict['other']->id === $this->id);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function sheet(): HasOne
    {
        return $this->hasOne(MatchSheet::class, 'match_id');
    }

    public function refereeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_user_id');
    }

    public function assistantUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_user_id');
    }

    public function scorerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scorer_user_id');
    }

    public function penaltyKicks(): HasMany
    {
        return $this->hasMany(MatchPenaltyKick::class, 'match_id')->orderBy('sequence')->orderBy('id');
    }

    public function lineups(): HasMany
    {
        return $this->hasMany(MatchLineup::class, 'match_id')->orderBy('sort_order')->orderBy('id');
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    public function feederMatches(): HasMany
    {
        return $this->hasMany(self::class, 'next_match_id');
    }

    public function periodLabel(): string
    {
        return match ($this->period) {
            '1t' => 'Primer tiempo',
            '2t' => 'Segundo tiempo',
            'et' => 'Alargue',
            'pen' => 'Penales',
            default => $this->period ?: 'Sin período',
        };
    }

    /**
     * @return array{home: int, away: int}
     */
    public function penaltyScore(): array
    {
        $homeId = $this->home_team_id;
        $awayId = $this->away_team_id;
        $kicks = $this->relationLoaded('penaltyKicks') ? $this->penaltyKicks : $this->penaltyKicks()->get();

        return [
            'home' => $kicks->where('team_id', $homeId)->where('scored', true)->count(),
            'away' => $kicks->where('team_id', $awayId)->where('scored', true)->count(),
        ];
    }

    public function penaltyWinnerId(): ?int
    {
        $score = $this->penaltyScore();
        if ($score['home'] === $score['away']) {
            return null;
        }

        return $score['home'] > $score['away'] ? $this->home_team_id : $this->away_team_id;
    }

    public function refereeLabel(): string
    {
        return $this->refereeUser?->name ?: ($this->referee_name ?: 'Sin asignar');
    }

    public function assistantLabel(): string
    {
        return $this->assistantUser?->name ?: ($this->sheet?->assistant_name ?: 'Sin asignar');
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllTournaments()) {
            return;
        }

        $clubIds = $user->scopedDelegationIds();
        if ($clubIds !== []) {
            $query->where(function (Builder $inner) use ($clubIds) {
                $inner->whereHas('homeTeam', fn (Builder $team) => $team->whereIn('delegation_id', $clubIds))
                    ->orWhereHas('awayTeam', fn (Builder $team) => $team->whereIn('delegation_id', $clubIds));
            });

            return;
        }

        $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]);
    }
}
