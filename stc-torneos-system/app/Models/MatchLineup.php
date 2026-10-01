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
        'shirt_number',
        'sort_order',
        'pos_x',
        'pos_y',
    ];

    protected function casts(): array
    {
        return [
            'starter' => 'boolean',
            'shirt_number' => 'integer',
            'sort_order' => 'integer',
            'pos_x' => 'integer',
            'pos_y' => 'integer',
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
