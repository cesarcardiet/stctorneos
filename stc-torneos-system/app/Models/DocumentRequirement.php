<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirement extends Model
{
    protected $fillable = [
        'tournament_id',
        'category_id',
        'type',
        'required',
        'has_expiration',
        'validity_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'has_expiration' => 'boolean',
            'validity_days' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultTypes(): array
    {
        return [
            'DNI frente',
            'DNI dorso',
            'Cobertura médica',
            'Foto del jugador',
            'Autorización',
            'Uso de imagen',
            'Apto médico',
        ];
    }

    public function scopeLabel(): string
    {
        if ($this->category) {
            return $this->category->name;
        }

        if ($this->tournament) {
            return $this->tournament->name;
        }

        return 'Todos los torneos';
    }

    public function obligationLabel(): string
    {
        return $this->required ? 'Obligatorio' : 'Opcional';
    }

    public function expirationLabel(): string
    {
        if (! $this->has_expiration) {
            return 'Sin vencimiento';
        }

        if ($this->validity_days) {
            return $this->validity_days.' días';
        }

        return 'Con vencimiento';
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllTournaments()) {
            return;
        }

        $ids = $user->assignedTournamentIds() ?: [0];
        $query->where(function (Builder $query) use ($ids) {
            $query
                ->whereNull('tournament_id')
                ->orWhereIn('tournament_id', $ids)
                ->orWhereHas('category', fn (Builder $category) => $category->whereIn('tournament_id', $ids));
        });
    }
}
