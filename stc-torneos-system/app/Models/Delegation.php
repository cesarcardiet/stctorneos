<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Delegation extends Model
{
    protected $fillable = [
        'tournament_id',
        'name',
        'country',
        'city',
        'logo_path',
        'delegate_name',
        'delegate_email',
        'delegate_phone',
        'additional_contacts',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'additional_contacts' => 'array',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function delegates(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function players(): HasManyThrough
    {
        return $this->hasManyThrough(Player::class, Team::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllTournaments()) {
            return;
        }

        $clubIds = $user->scopedDelegationIds();
        if ($clubIds !== []) {
            $query->whereIn('id', $clubIds);

            return;
        }

        $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]);
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'approved' => 'Aprobada',
            'pending' => 'Pendiente',
            'observed' => 'Observada',
            'blocked' => 'Bloqueada',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function originLabel(): string
    {
        return trim(collect([$this->city, $this->country])->filter()->implode(', ')) ?: 'Sin procedencia';
    }

    public function categoryYearsLabel(): string
    {
        $this->loadMissing('teams.category');

        $years = $this->teams
            ->map(fn (Team $team) => $team->category?->birth_year ?: $team->category?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $years->isEmpty() ? '—' : $years->implode(', ');
    }

    public function reviewActionLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Revisar',
            'observed' => 'Resolver',
            default => 'Ver',
        };
    }

    public function countryCode(): ?string
    {
        return \App\Support\Countries::guessCode($this->country);
    }

    public function syncLinkedRecords(): void
    {
        $this->teams()->update(['delegation_name' => $this->name]);
        $this->claimOrphanTeams();
    }

    public function claimOrphanTeams(): void
    {
        $name = mb_strtolower((string) $this->name);
        if ($name === '') {
            return;
        }

        Team::query()
            ->where('tournament_id', $this->tournament_id)
            ->where(function ($query) {
                $query->whereNull('delegation_id')->orWhere('delegation_id', 0);
            })
            ->where(function ($query) use ($name) {
                $query->whereRaw('LOWER(COALESCE(delegation_name, "")) = ?', [$name])
                    ->orWhereRaw('LOWER(name) = ?', [$name]);
            })
            ->update([
                'delegation_id' => $this->id,
                'delegation_name' => $this->name,
            ]);
    }

    public function unlinkTeams(): void
    {
        $delegateIds = $this->delegates()->pluck('users.id')->all();

        $this->teams()->update(['delegation_id' => null]);
        $this->delegates()->detach();

        foreach (User::query()->whereIn('id', $delegateIds)->get() as $user) {
            if ((int) $user->delegation_id === (int) $this->id) {
                $user->update([
                    'delegation_id' => $user->assignedDelegations()->value('delegations.id'),
                ]);
            }
        }
    }

    public static function resolveForClub(int $tournamentId, string $name, ?string $country = null): self
    {
        $name = trim($name) ?: 'Sin delegación';

        $club = static::query()
            ->where('tournament_id', $tournamentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($club) {
            return $club;
        }

        return static::create([
            'tournament_id' => $tournamentId,
            'name' => $name,
            'country' => $country ?: 'Argentina',
            'delegate_name' => '',
            'status' => 'approved',
        ]);
    }

    public function hasCustomLogo(): bool
    {
        return self::pathIsUsable($this->logo_path);
    }

    public function logoUrl(): string
    {
        $path = $this->hasCustomLogo() ? $this->logo_path : 'images/stc-logo.png';

        return str_starts_with((string) $path, 'http') ? $path : asset($path);
    }

    public function storeLogo(string $binary, string $ext = 'png'): bool
    {
        if (strlen($binary) < 24) {
            return false;
        }

        if (! in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
            $ext = 'png';
        }

        $directory = public_path('images/delegations');
        File::ensureDirectoryExists($directory);
        $filename = Str::slug($this->name ?: 'club').'-'.Str::random(8).'.'.$ext;
        File::put($directory.DIRECTORY_SEPARATOR.$filename, $binary);

        $old = $this->logo_path;
        $path = 'images/delegations/'.$filename;
        $this->update(['logo_path' => $path]);
        $this->teams()->update(['shield_path' => $path]);
        self::deleteUnusedPath($old);

        return true;
    }

    public function adoptExistingLogo(?string $path): void
    {
        if (! self::pathIsUsable($path)) {
            return;
        }

        $this->update(['logo_path' => $path]);
        $this->teams()->update(['shield_path' => $path]);
    }

    public static function knownLogoPathForName(string $name, ?int $exceptId = null): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $query = static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orderByDesc('updated_at');

        if ($exceptId) {
            $query->whereKeyNot($exceptId);
        }

        foreach ($query->get() as $delegation) {
            if ($delegation->hasCustomLogo()) {
                return $delegation->logo_path;
            }
        }

        return null;
    }

    public function reuseKnownLogoIfMissing(): bool
    {
        if ($this->hasCustomLogo()) {
            return false;
        }

        $path = static::knownLogoPathForName((string) $this->name, $this->id);
        if (! $path) {
            return false;
        }

        $this->adoptExistingLogo($path);

        return true;
    }

    public static function pathIsUsable(?string $path): bool
    {
        $path = trim((string) $path);
        if ($path === '' || $path === 'images/stc-logo.png') {
            return false;
        }

        if (str_starts_with($path, 'http')) {
            return true;
        }

        return is_file(public_path($path));
    }

    public static function deleteUnusedPath(?string $path): void
    {
        if (! self::pathIsUsable($path) || str_starts_with((string) $path, 'http')) {
            return;
        }

        if (! str_starts_with($path, 'images/teams/') && ! str_starts_with($path, 'images/delegations/')) {
            return;
        }

        $used = Team::query()->where('shield_path', $path)->exists()
            || static::query()->where('logo_path', $path)->exists();

        if (! $used && is_file(public_path($path))) {
            File::delete(public_path($path));
        }
    }

    public function assignDelegate(User $user, ?string $phone = null): void
    {
        $this->attachDelegate($user, $phone, $this->credentialDelegates()->where('id', '!=', $user->id)->isEmpty());
    }

    public function attachDelegate(User $user, ?string $phone = null, bool $asContact = false): void
    {
        $hadOtherClubs = $user->assignedDelegations()->exists();
        $this->delegates()->syncWithoutDetaching([$user->id]);

        $updates = [
            'delegation_id' => $this->id,
            'current_scope' => $this->name,
            'status' => $user->status === 'pending' ? 'active' : $user->status,
        ];

        if (! $hadOtherClubs) {
            $updates['tournament_id'] = $this->tournament_id;
        }

        $user->update($updates);

        if ($asContact || ! filled($this->delegate_email)) {
            $this->update([
                'delegate_name' => $user->name,
                'delegate_email' => $user->email,
                'delegate_phone' => $phone ?: ($this->delegate_phone ?: $user->phone),
            ]);
        }

        $this->unsetRelation('delegates');
    }

    /**
     * @param  list<User>  $users
     */
    public function syncDelegates(array $users): void
    {
        $keep = collect($users)->filter()->unique('id')->values();
        $keepIds = $keep->pluck('id')->map(fn ($id) => (int) $id)->all();

        $detachIds = $this->credentialDelegates()
            ->reject(fn (User $user) => in_array((int) $user->id, $keepIds, true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($detachIds !== []) {
            $this->delegates()->detach($detachIds);

            foreach (User::query()->whereIn('id', $detachIds)->get() as $user) {
                if ((int) $user->delegation_id === (int) $this->id) {
                    $user->update([
                        'delegation_id' => $user->assignedDelegations()->value('delegations.id'),
                    ]);
                }
            }
        }

        $this->unsetRelation('delegates');

        foreach ($keep as $index => $user) {
            $this->attachDelegate($user, null, $index === 0);
        }

        if ($keep->isEmpty()) {
            $this->update([
                'delegate_name' => '',
                'delegate_email' => null,
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function credentialDelegates()
    {
        $rows = $this->relationLoaded('delegates')
            ? $this->delegates
            : $this->delegates()->with('roles')->orderBy('name')->get();

        return $rows
            ->filter(fn (User $user) => $user->hasRole('delegado'))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function principalDelegate(): ?User
    {
        $delegates = $this->relationLoaded('delegates')
            ? $this->delegates
            : $this->delegates()->with('roles')->get();

        $withRole = $delegates->first(fn (User $user) => $user->hasRole('delegado'));
        if ($withRole) {
            return $withRole;
        }

        $email = trim((string) $this->delegate_email);
        if ($email === '') {
            return null;
        }

        return $delegates->first(fn (User $user) => strcasecmp((string) $user->email, $email) === 0);
    }

    public function contactName(): string
    {
        $name = trim((string) $this->delegate_name);
        if ($name !== '' && strcasecmp($name, trim((string) $this->name)) !== 0) {
            return $name;
        }

        return trim((string) ($this->principalDelegate()?->name ?? ''));
    }

    public function responsibleLabel(): string
    {
        $names = $this->relationLoaded('delegates')
            ? $this->credentialDelegates()->pluck('name')->filter()
            : collect();

        if ($names->count() > 1) {
            return 'Delegados: '.$names->implode(', ');
        }

        $name = $this->contactName();

        return $name !== '' ? 'Delegado: '.$name : 'Sin delegado';
    }

    /**
     * @return list<array{name: string, role: string, email: ?string, phone: ?string}>
     */
    public function additionalContacts(): array
    {
        return collect($this->additional_contacts ?? [])
            ->filter(fn ($row) => filled($row['name'] ?? null))
            ->map(fn ($row) => [
                'name' => (string) $row['name'],
                'role' => (string) ($row['role'] ?? 'Responsable'),
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{registered: int, complete: int, pending: int, enabled: int, docs_pending: int}
     */
    public function inscriptionStats(): array
    {
        $players = $this->relationLoaded('players')
            ? $this->players
            : $this->players()->get();

        $playerIds = $players->pluck('id');

        return [
            'registered' => $players->count(),
            'complete' => $players->whereIn('status', ['approved', 'enabled'])->count(),
            'pending' => $players->whereIn('status', ['draft', 'pending', 'observed'])->count(),
            'enabled' => $players->where('status', 'enabled')->count(),
            'docs_pending' => $playerIds->isEmpty()
                ? 0
                : PlayerDocument::query()
                    ->whereIn('player_id', $playerIds)
                    ->whereIn('status', ['pending', 'observed'])
                    ->count(),
        ];
    }
}
