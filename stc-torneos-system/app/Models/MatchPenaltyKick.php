<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPenaltyKick extends Model
{
    protected $fillable = [
        'match_id',
        'sequence',
        'team_id',
        'player_id',
        'scored',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scored' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function resultLabel(): string
    {
        return $this->scored ? 'Gol' : 'Errado';
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FixtureMatch::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
