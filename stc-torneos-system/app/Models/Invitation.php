<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation): void {
            if (! filled($invitation->token)) {
                $invitation->token = self::makeToken();
            }

            if (! filled($invitation->code)) {
                do {
                    $code = self::makeCode();
                } while (self::query()->where('code', $code)->exists());

                $invitation->code = $code;
            }
        });
    }

    protected $fillable = [
        'name',
        'email',
        'role_id',
        'status',
        'kind',
        'family_status',
        'token',
        'code',
        'scope_type',
        'scope_id',
        'player_id',
        'invited_by',
        'accepted_at',
        'accessed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'accessed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function familyStatusLabels(): array
    {
        return [
            'pending' => 'Pendiente',
            'generated' => 'Generada',
            'accessed' => 'Accedida',
            'in_progress' => 'En proceso',
            'completed' => 'Completada',
            'invalidated' => 'Invalidada',
        ];
    }

    public static function makeToken(): string
    {
        return Str::random(48);
    }

    public static function makeCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return collect(range(1, 8))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');
    }

    public function isStaff(): bool
    {
        return $this->kind === null || $this->kind === 'staff';
    }

    public function isMobileOnboardable(): bool
    {
        return $this->isStaff();
    }

    public function isGuardian(): bool
    {
        return $this->kind === 'guardian';
    }

    public function isPlayerPortal(): bool
    {
        return $this->kind === 'player';
    }

    public function isRoster(): bool
    {
        return $this->kind === 'roster';
    }

    public function isUsable(): bool
    {
        if ($this->isRoster()) {
            return $this->isRosterUsable();
        }

        if (! $this->isGuardian()) {
            return $this->status === 'pending';
        }

        if (in_array($this->family_status, ['invalidated', 'completed'], true)) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function canViewFicha(): bool
    {
        if ($this->family_status === 'completed' && $this->isGuardian() && $this->player_id) {
            return true;
        }

        return $this->isUsable();
    }

    public function familyStatusLabel(): string
    {
        return self::familyStatusLabels()[$this->family_status ?? 'pending'] ?? 'Pendiente';
    }

    public function isRosterUsable(): bool
    {
        if (! $this->isRoster()) {
            return false;
        }

        if ($this->family_status === 'invalidated') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function publicUrl(): string
    {
        if ($this->isRoster()) {
            return route('plantel.show', $this->token);
        }

        return route('ficha.show', $this->token);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
