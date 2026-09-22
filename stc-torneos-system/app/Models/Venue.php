<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Venue extends Model
{
    protected $fillable = [
        'tournament_id',
        'name',
        'city',
        'address',
        'map_url',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function mapUrl(): string
    {
        if ($this->map_url) {
            return $this->map_url;
        }

        $query = trim($this->address.' '.$this->city) ?: $this->name;

        return 'https://www.openstreetmap.org/search?query='.urlencode($query);
    }

    public function availableFieldsCount(): int
    {
        return $this->fields->filter(fn (Field $field) => $field->operationalStatus() === 'available')->count();
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }

    public function matches(): HasManyThrough
    {
        return $this->hasManyThrough(FixtureMatch::class, Field::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if (! $user->canAccessAllTournaments()) {
            $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]);
        }
    }
}
