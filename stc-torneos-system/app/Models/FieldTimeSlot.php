<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldTimeSlot extends Model
{
    protected $fillable = [
        'field_id',
        'weekday',
        'opens_at',
        'closes_at',
        'closed',
    ];

    protected function casts(): array
    {
        return [
            'closed' => 'boolean',
            'weekday' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function weekdayLabels(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }

    public function weekdayLabel(): string
    {
        return self::weekdayLabels()[$this->weekday] ?? 'Día';
    }

    public function rangeLabel(): string
    {
        if ($this->closed) {
            return 'Cerrada';
        }

        $open = $this->opens_at ? substr((string) $this->opens_at, 0, 5) : '—';
        $close = $this->closes_at ? substr((string) $this->closes_at, 0, 5) : '—';

        return $open.' a '.$close;
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
