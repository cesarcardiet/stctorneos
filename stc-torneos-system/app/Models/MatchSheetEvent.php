<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchSheetEvent extends Model
{
    protected $fillable = [
        'match_sheet_id',
        'type',
        'minute',
        'player_id',
        'team_staff_id',
        'team_id',
        'detail',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'goal' => 'Gol',
            'assist' => 'Asistencia',
            'yellow' => 'Amarilla',
            'red' => 'Roja',
            'substitution' => 'Cambio',
            'injury' => 'Lesión',
            'incident' => 'Incidencia',
            'motm' => 'Figura del partido',
            'staff_yellow' => 'Amarilla cuerpo técnico',
            'staff_red' => 'Roja cuerpo técnico',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? ucfirst((string) $this->type);
    }

    public function headline(): string
    {
        return match ($this->type) {
            'goal' => 'GOOL!',
            'yellow', 'staff_yellow' => 'TARJETA AMARILLA',
            'red', 'staff_red' => 'TARJETA ROJA',
            'assist' => 'ASISTENCIA',
            'substitution' => 'CAMBIO',
            default => mb_strtoupper($this->typeLabel()),
        };
    }

    public function tone(): string
    {
        return in_array($this->type, ['goal', 'yellow', 'red', 'assist', 'substitution', 'staff_yellow', 'staff_red'], true)
            ? match ($this->type) {
                'staff_yellow' => 'yellow',
                'staff_red' => 'red',
                default => $this->type,
            }
            : 'plain';
    }

    public function iconType(): string
    {
        return match ($this->type) {
            'staff_yellow' => 'yellow',
            'staff_red' => 'red',
            default => in_array($this->type, ['goal', 'yellow', 'red', 'assist', 'substitution'], true)
                ? $this->type
                : 'substitution',
        };
    }

    public function isStaffEvent(): bool
    {
        return in_array($this->type, ['staff_yellow', 'staff_red'], true) || $this->team_staff_id !== null;
    }

    public function isHomeSide(?int $homeTeamId): bool
    {
        return $homeTeamId !== null && (int) $this->team_id === (int) $homeTeamId;
    }

    public function minuteLabel(): string
    {
        return $this->minute !== null ? $this->minute."'" : '—';
    }

    public function actorName(): string
    {
        if ($this->teamStaff) {
            return $this->teamStaff->fullName();
        }

        return $this->player?->fullName() ?: ($this->team?->name ?: 'Sin jugador');
    }

    public function actorSubtitle(): string
    {
        if ($this->teamStaff) {
            return $this->teamStaff->roleLabel();
        }

        if ($this->player?->jersey_number) {
            return (string) $this->player->jersey_number;
        }

        return '';
    }

    public function actorPhotoUrl(): string
    {
        if ($this->teamStaff) {
            return $this->teamStaff->photoUrl();
        }

        if ($this->player) {
            return $this->player->photoUrl();
        }

        return $this->team?->shieldUrl() ?: asset('images/stc-logo.png');
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(MatchSheet::class, 'match_sheet_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function teamStaff(): BelongsTo
    {
        return $this->belongsTo(TeamStaff::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
