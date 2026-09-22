<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'current_scope',
        'tournament_id',
        'extra_tournament_id',
        'delegation_id',
        'player_id',
        'suspension_reason',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['scope_type', 'scope_id', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasRole(string $slug): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains('slug', $slug);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissionSlugs()->contains($slug);
    }

    public function hasAnyPermission(string ...$slugs): bool
    {
        if ($slugs === []) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $owned = $this->permissionSlugs();

        return collect($slugs)->contains(fn (string $slug) => $owned->contains($slug));
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'active' => 'Activo',
            'pending' => 'Pendiente',
            'suspended' => 'Suspendido',
            'revoked' => 'Revocado',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function primaryRole(): ?Role
    {
        $this->loadMissing('roles');

        return $this->roles->first();
    }

    public function roleLabel(): string
    {
        return $this->primaryRole()?->name ?? 'Sin rol';
    }

    public function credentialKind(): string
    {
        if ($this->hasRole('delegado')) {
            return 'Delegado';
        }
        if ($this->hasRole('asistente-arbitro')) {
            return 'Asistente/árbitro';
        }
        if ($this->hasRole('asistente-mesa')) {
            return 'Asistente de mesa';
        }
        if ($this->hasRole('jugador')) {
            return 'Jugador';
        }
        if ($this->hasRole('tutor')) {
            return 'Tutor';
        }
        if ($this->hasRole('admin-torneo')) {
            return 'Admin de torneo';
        }
        if ($this->hasRole('super-admin')) {
            return 'Administración general';
        }

        return $this->roleLabel();
    }

    public function tournamentLabel(): string
    {
        if ($this->hasRole('super-admin')) {
            return $this->current_scope ?: 'Torneo completo';
        }

        return $this->current_scope
            ?: $this->tournament?->name
            ?: 'Sin asignación';
    }

    public function sidebarScope(): string
    {
        if ($this->isSuperAdmin() || $this->canBrowseAllTournaments()) {
            return 'Todos los torneos';
        }

        $names = $this->accessibleTournaments()->pluck('name')->filter()->values();

        if ($names->isEmpty()) {
            return $this->tournamentLabel();
        }

        return $names->implode(' · ');
    }

    public function actionLabel(): string
    {
        if ($this->status === 'pending') {
            return 'Aprobar';
        }

        if ($this->hasRole('asistente-arbitro') || $this->hasRole('asistente-mesa')) {
            return 'Ver';
        }

        return 'Editar';
    }

    public function photoUrl(): string
    {
        if ($this->relationLoaded('player') && $this->player) {
            return $this->player->photoUrl();
        }

        $path = is_file(public_path('images/defaults/person.svg'))
            ? 'images/defaults/person.svg'
            : 'images/stc-logo.png';

        return asset($path);
    }

    public function actionRoute(): string
    {
        return $this->actionLabel() === 'Editar'
            ? route('admin.users.edit', $this)
            : route('admin.users.show', $this);
    }

    public function canAccessAllTournaments(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Árbitro / mesa / tutor: puede entrar a cualquier torneo en Operación (sin permisos de admin).
     */
    public function canBrowseAllTournaments(): bool
    {
        return $this->isPlayerAccount() || $this->isMatchStaffOnly() || $this->isTutorAccount();
    }

    public function isPlayerAccount(): bool
    {
        return $this->hasRole('jugador');
    }

    public function isTutorAccount(): bool
    {
        return $this->hasRole('tutor') && ! $this->isSuperAdmin();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Player>
     */
    public function tutorPlayers()
    {
        if (! $this->isTutorAccount()) {
            return Player::query()->whereRaw('1 = 0')->get();
        }

        $email = Str::lower(trim((string) $this->email));

        return Player::query()
            ->with([
                'team.category.tournament',
                'team.delegation',
                'guardian',
                'invitations' => fn ($query) => $query->where('kind', 'guardian')->latest('id'),
            ])
            ->whereHas('guardian', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Torneos donde el tutor tiene jugadores a cargo.
     *
     * @return list<int>
     */
    public function tutorTournamentIds(): array
    {
        if (! $this->isTutorAccount()) {
            return [];
        }

        return $this->tutorPlayers()
            ->loadMissing('team')
            ->pluck('team.tournament_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function linkedPlayer(): ?Player
    {
        if (! $this->isPlayerAccount() || ! $this->player_id) {
            return null;
        }

        return Player::query()->with(['team.category', 'team.tournament'])->find($this->player_id);
    }

    public function playerTournamentId(): ?int
    {
        $tournamentId = $this->linkedPlayer()?->team?->tournament_id;

        return $tournamentId ? (int) $tournamentId : null;
    }

    public function playerCategory(): ?Category
    {
        return $this->linkedPlayer()?->team?->category;
    }

    public function restrictsToAssignedClub(): bool
    {
        return $this->hasRole('delegado')
            && ! $this->hasRole('super-admin')
            && ! $this->hasRole('admin-torneo')
            && ! $this->hasPermission('tournaments.manage');
    }

    public function canAccessAdminWeb(): bool
    {
        return ! $this->isPlayerAccount()
            && ! $this->isTutorAccount()
            && ! $this->restrictsToAssignedClub();
    }

    public function defaultHomeRoute(): string
    {
        if ($this->isPlayerAccount()) {
            return 'workspace.player.home';
        }

        if ($this->isTutorAccount()) {
            return 'workspace.tutor.home';
        }

        if ($this->restrictsToAssignedClub()) {
            return 'workspace.home';
        }

        return 'dashboard';
    }

    /**
     * Árbitro / asistente de mesa: opera partidos, sin gestión de categoría.
     */
    public function isMatchStaffOnly(): bool
    {
        if ($this->restrictsToAssignedClub()) {
            return false;
        }

        if ($this->hasAnyPermission('tournaments.manage', 'delegations.manage', 'players.approve')) {
            return false;
        }

        return $this->hasAnyPermission('matches.manage', 'match_sheets.manage');
    }

    public function scopedDelegationId(?int $tournamentId = null): ?int
    {
        if (! $this->restrictsToAssignedClub()) {
            return null;
        }

        if ($tournamentId !== null) {
            $clubId = $this->assignedDelegations()
                ->where('tournament_id', $tournamentId)
                ->value('delegations.id');

            if ($clubId) {
                return (int) $clubId;
            }

            if ($this->delegation_id) {
                $legacy = Delegation::query()->find($this->delegation_id);
                if ($legacy && (int) $legacy->tournament_id === $tournamentId) {
                    return (int) $legacy->id;
                }
            }

            return null;
        }

        $ids = $this->scopedDelegationIds();

        return $ids[0] ?? null;
    }

    public function assignedClub(?int $tournamentId = null): ?Delegation
    {
        $clubId = $this->scopedDelegationId($tournamentId);

        return $clubId ? Delegation::query()->find($clubId) : null;
    }

    public function rosterTeamInCategory(Category $category): ?Team
    {
        if (! $this->restrictsToAssignedClub()) {
            return null;
        }

        $clubId = $this->scopedDelegationId((int) $category->tournament_id);
        if (! $clubId) {
            return null;
        }

        return Team::query()
            ->where('delegation_id', $clubId)
            ->where('category_id', $category->id)
            ->orderBy('name')
            ->first();
    }

    /**
     * @return list<int>
     */
    public function assignedTournamentIds(): array
    {
        if ($this->canAccessAllTournaments() || $this->canBrowseAllTournaments()) {
            return Tournament::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return collect([$this->tournament_id, $this->extra_tournament_id])
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function canAccessTournament(?int $tournamentId): bool
    {
        if ($this->canAccessAllTournaments() || $this->canBrowseAllTournaments()) {
            return true;
        }

        if ($this->restrictsToAssignedClub()) {
            return $tournamentId !== null;
        }

        return $tournamentId !== null && in_array($tournamentId, $this->assignedTournamentIds(), true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Tournament>
     */
    public function accessibleTournaments()
    {
        $query = Tournament::query()->orderBy('name');

        if ($this->restrictsToAssignedClub()) {
            return $query->get();
        }

        if (! $this->canAccessAllTournaments() && ! $this->canBrowseAllTournaments()) {
            $ids = $this->assignedTournamentIds();
            $query->whereIn('id', $ids === [] ? [0] : $ids);
        }

        return $query->get();
    }

    public function assignedTournamentsCount(): int
    {
        if ($this->canAccessAllTournaments()) {
            return max(1, Tournament::query()->count());
        }

        $count = count($this->assignedTournamentIds());

        return max($count, $this->current_scope ? 1 : 0);
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionSlugs(): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values();
    }

    /**
     * @return array<int, array{label: string, slugs: array<int, string>, limited: bool}>
     */
    public static function capabilityCatalog(): array
    {
        return [
            ['label' => 'Ver jugadores', 'slugs' => ['players.approve', 'delegations.manage'], 'limited' => false],
            ['label' => 'Editar lista buena fe', 'slugs' => ['players.approve'], 'limited' => false],
            ['label' => 'Subir documentación', 'slugs' => ['players.approve'], 'limited' => false],
            ['label' => 'Operar partido', 'slugs' => ['match_sheets.manage'], 'limited' => false],
            ['label' => 'Ver auditoría', 'slugs' => ['audit.view'], 'limited' => false],
            ['label' => 'Gestionar usuarios', 'slugs' => ['users.manage'], 'limited' => false],
            ['label' => 'Enviar comunicaciones', 'slugs' => ['communications.manage'], 'limited' => true],
            ['label' => 'Gestionar roles y permisos', 'slugs' => ['roles.manage'], 'limited' => false],
            ['label' => 'Administrar torneo', 'slugs' => ['tournaments.manage'], 'limited' => false],
        ];
    }

    /**
     * @param  Collection<int, string>|array<int, string>  $slugs
     * @return array<int, array{label: string, state: string, state_label: string}>
     */
    public static function capabilitiesForSlugs(Collection|array $slugs): array
    {
        $owned = collect($slugs);
        $isFullAdmin = $owned->contains('users.manage') && $owned->contains('roles.manage');

        return collect(self::capabilityCatalog())->map(function (array $item) use ($owned, $isFullAdmin) {
            $hasAll = collect($item['slugs'])->every(fn (string $slug) => $owned->contains($slug));
            $hasSome = collect($item['slugs'])->contains(fn (string $slug) => $owned->contains($slug));

            if ($hasAll && $item['limited'] && ! $isFullAdmin) {
                $state = 'limited';
            } elseif ($hasAll) {
                $state = 'active';
            } elseif ($hasSome) {
                $state = 'limited';
            } else {
                $state = 'denied';
            }

            return [
                'label' => $item['label'],
                'state' => $state,
                'state_label' => match ($state) {
                    'active' => 'Activo',
                    'limited' => 'Limitado',
                    default => 'No permitido',
                },
            ];
        })->all();
    }

    /**
     * @return array<int, array{label: string, state: string, state_label: string}>
     */
    public function capabilities(): array
    {
        return self::capabilitiesForSlugs($this->permissionSlugs());
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function extraTournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'extra_tournament_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function assignedDelegations(): BelongsToMany
    {
        return $this->belongsToMany(Delegation::class)->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function scopedDelegationIds(): array
    {
        if (! $this->restrictsToAssignedClub()) {
            return [];
        }

        $ids = $this->assignedDelegations()
            ->pluck('delegations.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === [] && $this->delegation_id) {
            return [(int) $this->delegation_id];
        }

        return $ids;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{club: Delegation, tournament: ?Tournament, teams: \Illuminate\Database\Eloquent\Collection<int, Team>}>
     */
    public function delegateClubPortfolio(): Collection
    {
        if (! $this->restrictsToAssignedClub()) {
            return collect();
        }

        return $this->assignedDelegations()
            ->with('tournament')
            ->orderBy('name')
            ->get()
            ->map(function (Delegation $club): array {
                $teams = Team::query()
                    ->with(['category.tournament'])
                    ->where('delegation_id', $club->id)
                    ->orderBy('category_id')
                    ->orderBy('name')
                    ->get();

                return [
                    'club' => $club,
                    'tournament' => $club->tournament,
                    'teams' => $teams,
                ];
            })
            ->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Team>
     */
    public function delegateTeams(): \Illuminate\Database\Eloquent\Collection
    {
        $clubIds = $this->scopedDelegationIds();
        if ($clubIds === []) {
            return Team::query()->whereRaw('1 = 0')->get();
        }

        return Team::query()
            ->with(['category.tournament', 'delegation'])
            ->whereIn('delegation_id', $clubIds)
            ->orderBy('tournament_id')
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();
    }

    public function favoriteTeams(): HasMany
    {
        return $this->hasMany(FavoriteTeam::class);
    }

    public function favoriteMatches(): HasMany
    {
        return $this->hasMany(FavoriteMatch::class);
    }

    public function initials(): string
    {
        $parts = collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)));

        return $parts->take(2)->implode('') ?: 'D';
    }

    public function whatsappUrl(?string $text = null): ?string
    {
        return \App\Support\WhatsApp::url($this->phone, $text);
    }
}
