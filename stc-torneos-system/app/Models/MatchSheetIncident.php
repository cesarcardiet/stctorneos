<?php

namespace App\Models;

use App\Support\FairPlayRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchSheetIncident extends Model
{
    protected $fillable = [
        'match_sheet_id',
        'type',
        'title',
        'related_name',
        'status',
        'fair_play_kind',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'applied' => 'Aplicada',
            'confirmed' => 'Confirmada',
            'pending' => 'Pendiente',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function actionLabel(): string
    {
        return $this->status === 'pending' ? 'Firmar' : 'Ver';
    }

    /**
     * @return array<string, string>
     */
    public static function fairPlayKindOptions(): array
    {
        return FairPlayRules::kindLabels();
    }

    public function fairPlayKindLabel(): ?string
    {
        $kind = trim((string) $this->fair_play_kind);

        return $kind !== '' ? (FairPlayRules::kindLabels()[$kind] ?? $kind) : null;
    }

    public function classifiedKind(): string
    {
        return FairPlayRules::classifyIncident($this);
    }

    public function fairPlayPoints(?Category $category = null): int
    {
        $kind = $this->classifiedKind();
        $scale = FairPlayRules::scale();

        if ($kind === 'generic') {
            $fallback = max(0, (int) ($category?->fair_play_incident ?? 3));

            return $fallback;
        }

        return $scale[$kind] ?? max(0, (int) ($category?->fair_play_incident ?? 3));
    }

    public function headline(): string
    {
        return trim((string) $this->title) ?: ($this->fairPlayKindLabel() ?? 'Incidencia Fair Play');
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(MatchSheet::class, 'match_sheet_id');
    }
}
