<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchSheet extends Model
{
    protected $fillable = [
        'match_id',
        'status',
        'validation_status',
        'current_step',
        'referee_name',
        'assistant_name',
        'responsible_name',
        'home_score',
        'away_score',
        'incident_title',
        'incident_team_id',
        'incident_moment',
        'incident_notes',
        'locked',
        'published',
        'published_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'locked' => 'boolean',
            'published' => 'boolean',
            'published_at' => 'datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'current_step' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Borrador',
            'loading' => 'En carga',
            'closed' => 'Cerrada',
            'observed' => 'Observada',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationLabels(): array
    {
        return [
            'pending' => 'Pendiente',
            'validated' => 'Validada',
            'review' => 'Revisión',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function validationLabel(): string
    {
        return self::validationLabels()[$this->validation_status] ?? ucfirst((string) $this->validation_status);
    }

    public function actionLabel(): string
    {
        return match ($this->status) {
            'closed' => 'Ver',
            'loading' => 'Abrir',
            'observed' => 'Resolver',
            default => 'Completar',
        };
    }

    public function responsibleLabel(): string
    {
        if ($this->responsible_name) {
            return $this->responsible_name;
        }

        if ($this->referee_name) {
            return 'Árbitro '.$this->referee_name;
        }

        return 'Mesa';
    }

    public function scoreLine(): string
    {
        return ($this->home_score ?? 0).' - '.($this->away_score ?? 0);
    }

    public function scoreTitle(): string
    {
        return trim(($this->match?->homeTeam?->name ?? 'Local').' '.$this->scoreLine().' '.($this->match?->awayTeam?->name ?? 'Visitante'));
    }

    public function matchDateLabel(): string
    {
        $date = $this->match?->scheduled_at;
        if (! $date) {
            return 'Sin horario';
        }

        $months = [1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'];

        return $date->format('d').' '.$months[(int) $date->month].' '.$date->format('Y').' · '.$date->format('H:i');
    }

    public function stepRoute(int $step): string
    {
        return match ($step) {
            2 => route('admin.sheets.eventos', $this),
            3 => route('admin.sheets.incidencias', $this),
            4 => route('admin.sheets.cierre', $this),
            default => route('admin.sheets.datos', $this),
        };
    }

    public function openRoute(): string
    {
        return $this->stepRoute((int) ($this->current_step ?: 1));
    }

    public function pendingSignaturesCount(): int
    {
        return $this->signatures->where('signed', false)->count();
    }

    public function recalcScore(): void
    {
        $goals = $this->events->where('type', 'goal');
        $homeId = $this->match?->home_team_id;
        $awayId = $this->match?->away_team_id;

        $this->update([
            'home_score' => $goals->where('team_id', $homeId)->count(),
            'away_score' => $goals->where('team_id', $awayId)->count(),
        ]);
    }

    public function ensureSignatures(): void
    {
        $defaults = [
            ['referee', $this->referee_name ?: 'Árbitro principal'],
            ['home_delegate', 'Delegado '.($this->match?->homeTeam?->name ?? 'local')],
            ['away_delegate', 'Delegado '.($this->match?->awayTeam?->name ?? 'visitante')],
        ];

        foreach ($defaults as [$role, $name]) {
            $this->signatures()->firstOrCreate(
                ['role' => $role],
                ['name' => $name, 'signed' => $role === 'referee' && (bool) $this->referee_name]
            );
        }
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FixtureMatch::class, 'match_id');
    }

    public function incidentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'incident_team_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchSheetEvent::class)->orderBy('minute')->orderBy('id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(MatchSheetIncident::class)->latest();
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(MatchSheetSignature::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllTournaments()) {
            return;
        }

        $query->whereHas('match', fn (Builder $match) => $match->accessibleTo($user));
    }
}
