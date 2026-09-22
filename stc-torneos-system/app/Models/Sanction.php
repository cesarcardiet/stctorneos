<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sanction extends Model
{
    protected $fillable = [
        'tournament_id',
        'category_id',
        'team_id',
        'player_id',
        'match_id',
        'type',
        'title',
        'resolution',
        'points_delta',
        'status',
        'created_by',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'points_delta' => 'integer',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'points' => 'Puntos en tabla',
            'suspension' => 'Suspensión',
            'warning' => 'Apercibimiento',
            'fine' => 'Resolución disciplinaria',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'active' => 'Vigente',
            'resolved' => 'Resuelta',
            'revoked' => 'Revocada',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? ucfirst((string) $this->type);
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function pointsLabel(): string
    {
        $delta = (int) $this->points_delta;
        if ($delta === 0) {
            return 'Sin impacto';
        }

        return ($delta > 0 ? '+' : '').$delta.' pts';
    }

    public function subjectLabel(): string
    {
        return $this->player?->fullName()
            ?: $this->team?->name
            ?: $this->category?->name
            ?: $this->tournament?->name
            ?: 'Sin sujeto';
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FixtureMatch::class, 'match_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllTournaments()) {
            return;
        }

        $clubIds = $user->scopedDelegationIds();
        if ($clubIds !== []) {
            $query->where(function (Builder $inner) use ($clubIds) {
                $inner->whereHas('team', fn (Builder $team) => $team->whereIn('delegation_id', $clubIds))
                    ->orWhereHas('player.team', fn (Builder $team) => $team->whereIn('delegation_id', $clubIds));
            });

            return;
        }

        $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
