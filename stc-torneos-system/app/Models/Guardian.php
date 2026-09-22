<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guardian extends Model
{
    protected $fillable = [
        'player_id',
        'name',
        'relationship',
        'document_number',
        'email',
        'phone',
        'alternate_contact',
        'consent_status',
    ];

    /**
     * @return array<string, string>
     */
    public static function consentLabels(): array
    {
        return [
            'pending' => 'Pendiente de tutor',
            'approved' => 'Aprobado por tutor',
            'rejected' => 'Rechazado por tutor',
        ];
    }

    /**
     * @return list<string>
     */
    public static function relationshipOptions(): array
    {
        return ['Madre', 'Padre', 'Tutor legal', 'Familiar', 'Delegado'];
    }

    public function consentLabel(): string
    {
        return self::consentLabels()[$this->consent_status ?? 'pending'] ?? 'Pendiente de tutor';
    }

    public function isApproved(): bool
    {
        return ($this->consent_status ?? 'pending') === 'approved';
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
