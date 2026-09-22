<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchLineup extends Model
{
    protected $fillable = [
        'match_id',
        'team_id',
        'player_id',
        'starter',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'starter' => 'boolean',
            'sort_order' => 'integer',
        ];
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
