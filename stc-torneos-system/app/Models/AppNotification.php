<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AppNotification extends Model
{
    protected $fillable = [
        'tournament_id',
        'content_post_id',
        'created_by',
        'channel',
        'title',
        'body',
        'audience',
        'status',
        'recipients_count',
        'read_count',
        'scheduled_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function channelLabels(): array
    {
        return [
            'in_app' => 'En app',
            'email' => 'Correo',
            'both' => 'App + correo',
            'push' => 'Push (próximamente)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Borrador',
            'scheduled' => 'Programada',
            'sent' => 'Enviada',
            'failed' => 'Fallida',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function audienceLabels(): array
    {
        return ContentPost::audienceLabels();
    }

    public function channelLabel(): string
    {
        return self::channelLabels()[$this->channel] ?? ucfirst((string) $this->channel);
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function audienceLabel(): string
    {
        return self::audienceLabels()[$this->audience] ?? ucfirst((string) $this->audience);
    }

    public function actionLabel(): string
    {
        return $this->status === 'draft' || $this->status === 'scheduled' ? 'Enviar' : 'Ver';
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveAudienceUsers(?User $actor = null): Collection
    {
        $query = User::query()->with('roles')->where('status', 'active');

        if ($actor && ! $actor->canAccessAllTournaments()) {
            $query->whereIn('tournament_id', $actor->assignedTournamentIds() ?: [0]);
        }

        return match ($this->audience) {
            'delegates' => $query->whereHas('roles', fn ($query) => $query->where('slug', 'delegado'))->get(),
            'referees' => $query->whereHas('roles', fn ($query) => $query->where('slug', 'arbitro'))->get(),
            'staff' => $query->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super-admin', 'admin-torneo', 'coordinador', 'asistente-mesa']))->get(),
            'public' => collect(),
            default => $query->get(),
        };
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if (! $user->canAccessAllTournaments()) {
            $ids = $user->assignedTournamentIds() ?: [0];
            $query->where(function (Builder $query) use ($ids) {
                $query->whereNull('tournament_id')->orWhereIn('tournament_id', $ids);
            });
        }
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'content_post_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AppNotificationRecipient::class);
    }
}
