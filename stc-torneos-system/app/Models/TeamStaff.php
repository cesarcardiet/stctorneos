<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamStaff extends Model
{
    protected $table = 'team_staff';

    protected $fillable = [
        'team_id',
        'first_name',
        'last_name',
        'document_number',
        'role',
        'photo_path',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            'director_tecnico' => 'Director Técnico',
            'preparador_fisico' => 'Preparador Físico',
            'ayudante_campo' => 'Ayudante de Campo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'active' => 'Activo',
            'withdrawn' => 'Retirado',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function roleLabel(): string
    {
        return self::roleLabels()[$this->role] ?? ucfirst(str_replace('_', ' ', (string) $this->role));
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function photoUrl(): string
    {
        $path = trim((string) $this->photo_path);
        $fallback = Player::defaultPhotoPath();

        if ($path === '' || $path === 'images/stc-logo.png' || (! str_starts_with($path, 'http') && ! is_file(public_path($path)))) {
            $path = $fallback;
        }

        return str_starts_with($path, 'http') ? $path : asset($path);
    }

    public function formattedDocument(): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->document_number);

        if (! $digits) {
            return 'Sin documento';
        }

        return number_format((int) $digits, 0, ',', '.');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
