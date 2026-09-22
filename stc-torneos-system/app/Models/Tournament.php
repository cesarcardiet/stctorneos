<?php

namespace App\Models;

use App\Support\ShieldPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'edition',
        'slug',
        'logo_path',
        'country',
        'city',
        'venue_name',
        'timezone',
        'location',
        'starts_at',
        'ends_at',
        'status',
        'description',
        'contact_name',
        'contact_email',
        'contact_phone',
        'rules_url',
        'general_info',
        'visibility',
        'registration_starts_at',
        'registration_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'registration_starts_at' => 'date',
            'registration_ends_at' => 'date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Borrador',
            'registration' => 'Inscripción',
            'preparation' => 'Preparación',
            'in_progress' => 'En curso',
            'finished' => 'Finalizado',
            'archived' => 'Archivado',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'in_progress', 'registration', 'preparation' => 'is-approved',
            'finished' => 'is-finished',
            'draft' => 'is-pending',
            default => 'is-blocked',
        };
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isPublished(): bool
    {
        return $this->visibility === 'public' && ! in_array($this->status, ['draft', 'archived'], true);
    }

    /**
     * @return list<string>
     */
    public function publishBlockers(): array
    {
        $required = [
            'name' => 'nombre',
            'edition' => 'edición',
            'country' => 'país',
            'city' => 'ciudad',
            'venue_name' => 'sede',
            'timezone' => 'zona horaria',
            'starts_at' => 'fecha de inicio',
            'ends_at' => 'fecha de finalización',
            'contact_name' => 'contacto',
            'contact_email' => 'email de contacto',
            'description' => 'descripción',
        ];

        $missing = [];

        foreach ($required as $field => $label) {
            if (blank($this->{$field})) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function canPublish(): bool
    {
        return $this->publishBlockers() === [] && ! $this->isArchived();
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(Delegation::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FixtureMatch::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function resolvedLogoPath(): string
    {
        $path = trim((string) $this->logo_path) ?: 'images/stc-logo.png';

        if (! str_starts_with($path, 'http') && ! is_file(public_path($path))) {
            return 'images/stc-logo.png';
        }

        return $path;
    }

    public function logoUrl(): string
    {
        $path = $this->resolvedLogoPath();

        return str_starts_with($path, 'http') ? $path : asset($path);
    }

    public function applyLogoFromRequest(Request $request): bool
    {
        $payload = ShieldPayload::fromRequest($request)
            ?? ShieldPayload::fromRequest($request, 'logo_file');

        if ($payload) {
            return $this->storeLogo($payload[0], $payload[1]);
        }

        return false;
    }

    public function storeLogo(string $binary, string $ext = 'png'): bool
    {
        if (strlen($binary) < 24) {
            return false;
        }

        if (! in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
            $ext = 'png';
        }

        $directory = public_path('images/tournaments');
        File::ensureDirectoryExists($directory);
        $filename = Str::slug($this->name ?: 'torneo').'-'.Str::random(8).'.'.$ext;
        File::put($directory.DIRECTORY_SEPARATOR.$filename, $binary);

        $old = $this->logo_path;
        $path = 'images/tournaments/'.$filename;
        $this->update(['logo_path' => $path]);
        $this->resetCategoryBannersToDefault();
        $this->deleteUnusedLogo($old);

        return true;
    }

    public function resetCategoryBannersToDefault(): void
    {
        $this->categories()->update(['image_path' => null]);
    }

    private function deleteUnusedLogo(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '' || str_starts_with($path, 'http') || ! str_starts_with($path, 'images/tournaments/')) {
            return;
        }

        if (static::query()->where('logo_path', $path)->where('id', '!=', $this->id)->exists()) {
            return;
        }

        if (is_file(public_path($path))) {
            File::delete(public_path($path));
        }
    }
}
