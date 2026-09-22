<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ContentPost extends Model
{
    protected $fillable = [
        'tournament_id',
        'category_id',
        'author_id',
        'type',
        'title',
        'slug',
        'summary',
        'body',
        'cover_path',
        'audience',
        'status',
        'pinned',
        'scheduled_at',
        'published_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'news' => 'Noticia',
            'announcement' => 'Comunicado',
            'official' => 'Contenido oficial',
            'plaque' => 'Placa',
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
            'published' => 'Publicada',
            'archived' => 'Archivada',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function audienceLabels(): array
    {
        return [
            'public' => 'Público / app',
            'all' => 'Todos los usuarios',
            'delegates' => 'Delegados',
            'referees' => 'Árbitros',
            'staff' => 'Staff interno',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? ucfirst((string) $this->type);
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function audienceLabel(): string
    {
        return self::audienceLabels()[$this->audience] ?? ucfirst((string) $this->audience);
    }

    public function coverUrl(): string
    {
        $path = $this->cover_path ?: 'images/category-banner.svg';

        return str_starts_with($path, 'http') ? $path : asset($path);
    }

    public function actionLabel(): string
    {
        return match ($this->status) {
            'published' => 'Ver',
            'archived' => 'Ver',
            default => 'Editar',
        };
    }

    public static function makeSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'contenido';
        $slug = $base;
        $i = 2;

        while (static::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }
}
