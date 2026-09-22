<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Field extends Model
{
    protected $fillable = [
        'venue_id',
        'name',
        'surface',
        'modality',
        'lighting',
        'opens_at',
        'closes_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'lighting' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'available' => 'Activa',
            'occupied' => 'Ocupada',
            'blocked' => 'Bloqueada',
            'maintenance' => 'Mantenimiento',
            'unavailable' => 'No disponible',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->operationalStatus()] ?? ucfirst((string) $this->status);
    }

    public function availabilityLabel(): string
    {
        return match ($this->operationalStatus()) {
            'available' => 'Activa',
            'occupied' => 'Ocupada',
            'blocked' => 'Bloqueada',
            'maintenance' => 'Mantenimiento',
            'unavailable' => 'No disponible',
            default => $this->statusLabel(),
        };
    }

    public function detailStatusPhrase(): string
    {
        return match ($this->operationalStatus()) {
            'available' => 'estado activa',
            'occupied' => 'estado ocupada',
            'blocked' => 'estado bloqueada',
            'maintenance' => 'estado en mantenimiento',
            'unavailable' => 'estado no disponible',
            default => 'estado '.$this->statusLabel(),
        };
    }

    public function heroMeta(): string
    {
        return collect([
            $this->name,
            $this->surface,
            $this->modality,
            $this->venue?->address,
        ])->filter()->implode(' · ');
    }

    public function operationalStatus(): string
    {
        if (in_array($this->status, ['maintenance', 'blocked', 'unavailable'], true)) {
            return $this->status;
        }

        if ($this->status === 'occupied' || $this->isBusyNow()) {
            return 'occupied';
        }

        return 'available';
    }

    public function isBusyNow(): bool
    {
        return $this->matches
            ->where('status', 'live')
            ->isNotEmpty();
    }

    public function hasConflict(): bool
    {
        if ($this->status === 'maintenance' && $this->upcomingMatches()->isNotEmpty()) {
            return true;
        }

        return FixtureMatch::detectConflicts(
            $this->matches->filter(fn (FixtureMatch $match) => $match->status !== 'finished')
        )->isNotEmpty();
    }

    public function conflictLabel(): string
    {
        return $this->hasConflict() ? 'Sí' : 'No';
    }

    public function lightingLabel(): string
    {
        return $this->lighting ? 'Sí' : 'No';
    }

    public function upcomingMatches()
    {
        return $this->matches
            ->filter(fn (FixtureMatch $match) => in_array($match->status, ['scheduled', 'rescheduled', 'live'], true))
            ->sortBy('scheduled_at')
            ->values();
    }

    public function scheduleLabel(): string
    {
        $open = $this->formatClock($this->opens_at) ?: '08:00';
        $close = $this->formatClock($this->closes_at) ?: '20:00';

        return $open.'–'.$close;
    }

    public function actionLabel(): string
    {
        return 'Ver';
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FixtureMatch::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(FieldTimeSlot::class)->orderBy('weekday');
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if (! $user->canAccessAllTournaments()) {
            $query->whereHas('venue', fn (Builder $venue) => $venue->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]));
        }
    }

    private function formatClock(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return is_string($value) ? substr($value, 0, 5) : null;
        }
    }

    public function imageUrl(): string
    {
        $path = is_file(public_path('images/defaults/field.svg'))
            ? 'images/defaults/field.svg'
            : 'images/stc-logo.png';

        return asset($path);
    }
}
