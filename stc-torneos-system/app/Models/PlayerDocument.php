<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PlayerDocument extends Model
{
    protected $fillable = [
        'player_id',
        'type',
        'status',
        'file_path',
        'original_name',
        'uploaded_by_name',
        'uploaded_at',
        'notes',
        'checklist',
        'reviewed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'pending' => 'Pendiente',
            'observed' => 'Observado',
            'rejected' => 'Rechazado',
            'approved' => 'Aprobado',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function actionLabel(): string
    {
        return match ($this->status) {
            'observed' => 'Resolver',
            'approved', 'rejected' => 'Ver',
            default => 'Revisar',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function checklistItems(): array
    {
        return [
            'identity_match' => 'Identidad coincide',
            'tutor_signed' => 'Tutor firmó',
            'expiration_valid' => 'Vencimiento vigente',
            'file_readable' => 'Archivo legible',
            'fit_to_play' => 'Apto para jugar',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function checklistStatus(): array
    {
        $defaults = [
            'identity_match' => 'ok',
            'tutor_signed' => 'ok',
            'expiration_valid' => 'warn',
            'file_readable' => 'ok',
            'fit_to_play' => 'warn',
        ];

        return array_merge($defaults, $this->checklist ?? []);
    }

    public function fileUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return str_starts_with($this->file_path, 'http') ? $this->file_path : asset($this->file_path);
    }

    public function isImage(): bool
    {
        $path = strtolower((string) $this->file_path);

        return $path !== '' && Str::endsWith($path, ['.png', '.jpg', '.jpeg', '.webp', '.gif']);
    }

    public function isHtmlDocument(): bool
    {
        $path = strtolower((string) $this->file_path);

        return $path !== '' && Str::endsWith($path, ['.html', '.htm']);
    }

    public function requiresClubApproval(): bool
    {
        return self::requiresClubReviewForType((string) $this->type);
    }

    public static function requiresClubReviewForType(string $type): bool
    {
        if ($type === Player::guardianCertificateType()) {
            return false;
        }

        return in_array($type, Player::uploadDocumentTypes(), true);
    }

    public function wasSignedByGuardian(): bool
    {
        if ($this->type === Player::guardianCertificateType()) {
            return $this->status === 'approved' && filled($this->file_path);
        }

        return $this->status === 'approved'
            && filled($this->uploaded_by_name)
            && in_array($this->type, Player::authorizationDocumentTypes(), true);
    }

    public function storeImage(UploadedFile $file, ?string $uploadedBy = null): void
    {
        $original = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $binary = file_get_contents($file->getRealPath());
        if ($binary === false) {
            throw new \RuntimeException('No se pudo leer la imagen subida.');
        }

        $this->storeBinary($binary, $ext, $uploadedBy, $original);
    }

    public function storeBinary(string $binary, string $ext = 'png', ?string $uploadedBy = null, ?string $originalName = null): void
    {
        $ext = strtolower($ext);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (! in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
            $ext = 'png';
        }

        $playerId = (int) $this->player_id;
        $isPlayerPhoto = $this->type === 'Foto del jugador' && $playerId > 0;

        if ($isPlayerPhoto) {
            $directory = public_path('images/players');
            File::ensureDirectoryExists($directory);
            $filename = 'player-'.$playerId.'-'.Str::random(8).'.'.$ext;
            $relative = 'images/players/'.$filename;
        } else {
            $directory = public_path('images/players/docs');
            File::ensureDirectoryExists($directory);
            $filename = Str::slug($this->type).'-'.$playerId.'-'.Str::random(8).'.'.$ext;
            $relative = 'images/players/docs/'.$filename;
        }

        File::put($directory.DIRECTORY_SEPARATOR.$filename, $binary);

        $this->rememberStoredFile(
            $relative,
            $originalName ?: Str::slug((string) $this->type).'.'.$ext,
            $uploadedBy,
            $isPlayerPhoto
        );
    }

    private function rememberStoredFile(string $path, string $original, ?string $uploadedBy, bool $approvePhoto = false): void
    {
        $old = $this->file_path;
        $this->update([
            'file_path' => $path,
            'original_name' => $original,
            'uploaded_by_name' => $uploadedBy ?: $this->uploaded_by_name,
            'uploaded_at' => now(),
            'status' => $approvePhoto ? 'approved' : 'pending',
        ]);

        $this->deleteStoredFileIfOwned($old, $path);
    }

    private function deleteStoredFileIfOwned(?string $old, string $keep): void
    {
        $old = trim((string) $old);
        if ($old === '' || $old === $keep) {
            return;
        }

        $owned = str_starts_with($old, 'images/players/docs/')
            || preg_match('#^images/players/(player|portrait)-\d+#', $old);

        if (! $owned || ! is_file(public_path($old))) {
            return;
        }

        File::delete(public_path($old));
    }

    public function previewName(): string
    {
        return $this->original_name ?: Str::slug($this->type, '_').'.pdf';
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function expirationLabel(): string
    {
        if (! $this->expires_at) {
            return 'Sin vencimiento';
        }

        return ($this->isExpired() ? 'Vencido ' : 'Vence ').$this->expires_at->format('d/m/Y');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllTournaments()) {
            return;
        }

        $clubIds = $user->scopedDelegationIds();
        if ($clubIds !== []) {
            $query->whereHas('player.team', fn (Builder $team) => $team->whereIn('delegation_id', $clubIds));

            return;
        }

        $query->whereHas('player.team', fn (Builder $team) => $team->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]));
    }
}
