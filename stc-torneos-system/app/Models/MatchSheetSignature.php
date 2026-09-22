<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchSheetSignature extends Model
{
    protected $fillable = [
        'match_sheet_id',
        'role',
        'name',
        'signed',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'signed' => 'boolean',
            'signed_at' => 'datetime',
        ];
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'referee' => 'Árbitro principal',
            'home_delegate' => 'Delegado local',
            'away_delegate' => 'Delegado visitante',
            default => ucfirst((string) $this->role),
        };
    }

    public function statusPhrase(): string
    {
        return $this->roleLabel().': '.$this->name.' · '.($this->signed ? 'Firmado' : 'Pendiente');
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(MatchSheet::class, 'match_sheet_id');
    }
}
