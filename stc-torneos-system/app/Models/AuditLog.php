<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'description',
        'auditable_type',
        'auditable_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'create' => 'Alta de registro',
            'update' => 'Modificación de registro',
            'delete' => 'Eliminación',
            'status_update' => 'Cambio de estado',
            'publish' => 'Publicación',
            'send' => 'Envío de comunicación',
            'observe' => 'Observación',
            'seed_demo_data' => 'Carga inicial',
            default => $this->action ? str_replace('_', ' ', $this->action) : 'Evento',
        };
    }

    public function recordLabel(): string
    {
        $meta = $this->metadata ?? [];

        foreach (['name', 'title', 'score', 'email'] as $key) {
            if (! empty($meta[$key]) && is_scalar($meta[$key])) {
                $prefix = $this->auditable_id ? class_basename((string) $this->auditable_type).' #'.$this->auditable_id.' · ' : '';

                return $prefix.$meta[$key];
            }
        }

        if ($this->auditable_type && $this->auditable_id) {
            return class_basename($this->auditable_type).' #'.$this->auditable_id.' · '.($this->module ?: 'Sistema');
        }

        return $this->module ?: 'Sistema';
    }

    public function happenedAtLabel(): string
    {
        if (! $this->created_at) {
            return '—';
        }

        if ($this->created_at->isToday()) {
            return 'Hoy '.$this->created_at->format('H:i');
        }

        if ($this->created_at->isYesterday()) {
            return 'Ayer '.$this->created_at->format('H:i');
        }

        return $this->created_at->format('d/m/Y H:i');
    }

    public function referenceCode(): string
    {
        $year = $this->created_at?->format('Y') ?: now()->format('Y');

        return 'AUD-'.$year.'-'.$this->id;
    }

    public function isReviewed(): bool
    {
        return filled(data_get($this->metadata, 'reviewed_at'));
    }

    public function isCritical(): bool
    {
        return in_array($this->action, ['delete', 'status_update', 'publish', 'send'], true);
    }

    public function isAlert(): bool
    {
        return $this->isCritical()
            || in_array($this->module, ['Planillas', 'Resultados', 'Usuarios'], true)
            || str_contains(mb_strtolower((string) $this->description), 'permiso');
    }

    public function previousValue(): string
    {
        return $this->metadataText('before', 'previous', 'old', 'old_value', 'previous_status') ?: '—';
    }

    public function newValue(): string
    {
        return $this->metadataText('after', 'new', 'score', 'status', 'name', 'title') ?: '—';
    }

    public function reason(): string
    {
        return $this->metadataText('reason', 'motivo', 'notes') ?: ($this->description ?: '—');
    }

    public function relevanceLabel(): string
    {
        if ($this->isCritical()) {
            return 'Acción crítica';
        }

        if ($this->isAlert()) {
            return 'Acción relevante';
        }

        return 'Registro operativo';
    }

    private function metadataText(string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($this->metadata, $key);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                return $value ? 'Sí' : 'No';
            }

            if (is_array($value)) {
                return implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item), $value));
            }

            return (string) $value;
        }

        return null;
    }
}
