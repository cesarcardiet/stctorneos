<?php

namespace App\Http\Controllers\Workspace\Concerns;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Delegation;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Support\DateTimeInput;
use Illuminate\Http\Request;

trait ResolvesWorkspace
{
    protected function assertCategory(Category $category): Category
    {
        $user = auth()->user();
        abort_unless(
            $user?->canAccessTournament((int) $category->tournament_id),
            403,
            'Esta categoría no está dentro de tu alcance.'
        );

        $category = $category->loadMissing('tournament');
        \App\Support\WorkspaceContext::rememberCategory($category);

        return $category;
    }

    protected function assertTournament(Tournament $tournament): Tournament
    {
        abort_unless(
            auth()->user()?->canAccessTournament((int) $tournament->id),
            403,
            'Este torneo no está dentro de tu alcance.'
        );

        return $tournament;
    }

    protected function assertClubVisible(?User $user, Delegation $club): void
    {
        abort_unless($user, 403);
        abort_unless(
            Delegation::query()->accessibleTo($user)->whereKey($club->id)->exists(),
            403,
            'Este club no es tu asignación.'
        );
    }

    protected function isMatchStaffOnly(?User $user): bool
    {
        return (bool) $user?->isMatchStaffOnly();
    }

    protected function canEdit(?User $user): bool
    {
        if ($user?->isTutorAccount()) {
            return false;
        }

        if ($user?->restrictsToAssignedClub()) {
            return false;
        }

        // Árbitro / mesa: ven equipos y juegan resultados, no configuran la categoría.
        if ($this->isMatchStaffOnly($user)) {
            return false;
        }

        return (bool) $user?->hasAnyPermission(
            'tournaments.manage',
            'delegations.manage',
            'matches.manage',
            'players.approve',
        );
    }

    protected function canManageRoster(?User $user): bool
    {
        if ($user?->isTutorAccount()) {
            return false;
        }

        if ($this->isMatchStaffOnly($user)) {
            return false;
        }

        return (bool) $user?->hasAnyPermission(
            'delegations.manage',
            'players.approve',
            'tournaments.manage',
        );
    }

    protected function canEditFullPlayer(?User $user): bool
    {
        if ($user?->restrictsToAssignedClub()) {
            return $this->canManageRoster($user);
        }

        if ($this->isMatchStaffOnly($user)) {
            return false;
        }

        return $this->canEdit($user);
    }

    protected function canViewTeam(?User $user, Category $category, Team $team): bool
    {
        return (int) $team->category_id === (int) $category->id;
    }

    protected function canEditTeam(?User $user, Team $team): bool
    {
        if ($this->isMatchStaffOnly($user)) {
            return false;
        }

        if ($user?->restrictsToAssignedClub()) {
            return Team::query()->accessibleTo($user)->whereKey($team->id)->exists();
        }

        return $this->canEdit($user);
    }

    protected function canManageTeamRoster(?User $user, Team $team): bool
    {
        if ($this->isMatchStaffOnly($user)) {
            return false;
        }

        if ($user?->restrictsToAssignedClub()) {
            $ownsTeam = Team::query()->accessibleTo($user)->whereKey($team->id)->exists();
            if (! $ownsTeam) {
                return false;
            }

            $team->loadMissing('category.tournament');

            return (bool) $team->category?->acceptsRosterEdits($team);
        }

        return $this->canManageRoster($user);
    }

    protected function canShareRosterLink(?User $user, Team $team): bool
    {
        return $this->canManageTeamRoster($user, $team);
    }

    protected function rosterLockReasonFor(?User $user, Team $team): ?string
    {
        if ($this->isMatchStaffOnly($user)) {
            $team->loadMissing('category');
            if ($team->category?->registrationsOpen()) {
                return null;
            }

            return 'Las inscripciones están cerradas. No se pueden editar jugadores.';
        }

        if (! $user?->restrictsToAssignedClub()) {
            return null;
        }

        if (! Team::query()->accessibleTo($user)->whereKey($team->id)->exists()) {
            return null;
        }

        $team->loadMissing('category.tournament');
        $category = $team->category;
        if (! $category || $category->acceptsRosterEdits($team)) {
            return null;
        }

        return $category->rosterEditBlockedReason($team);
    }

    protected function assertDelegateRosterEditable(?User $user, Team $team): void
    {
        abort_unless(
            $this->canManageTeamRoster($user, $team),
            403,
            $this->rosterLockReasonFor($user, $team) ?? 'No podés modificar este plantel.'
        );
    }

    protected function canViewPlayer(?User $user, Category $category, Player $player): bool
    {
        return (int) $player->team?->category_id === (int) $category->id;
    }

    protected function canEditPlayer(?User $user, Player $player): bool
    {
        $team = $player->team;
        if (! $team) {
            return false;
        }

        if ($user?->restrictsToAssignedClub()) {
            return $this->canManageTeamRoster($user, $team);
        }

        if ($this->isMatchStaffOnly($user)) {
            $team->loadMissing('category');

            return (bool) $team->category?->registrationsOpen();
        }

        return $this->canEditFullPlayer($user);
    }

    protected function canScheduleMatches(?User $user): bool
    {
        return (bool) $user?->hasPermission('tournaments.manage');
    }

    protected function canOperateMatch(?User $user): bool
    {
        if (! $user || $user->hasRole('delegado')) {
            return false;
        }

        return $user->hasAnyPermission('matches.manage', 'match_sheets.manage');
    }

    protected function canManageClubs(?User $user): bool
    {
        if ($user?->isTutorAccount()) {
            return false;
        }

        return (bool) $user && ! $user->restrictsToAssignedClub() && $user->hasAnyPermission(
            'tournaments.manage',
            'users.manage',
            'delegations.manage',
        );
    }

    protected function canAssignDelegates(?User $user): bool
    {
        return $this->canManageClubs($user);
    }

    protected function assertCategoryManagement(?User $user): void
    {
        abort_unless($this->canEdit($user), 403, 'No podés modificar esta categoría.');
    }

    protected function fixtureImageUrl(Category $category): string
    {
        $path = trim((string) $category->workspaceValue('fixture_image_path', ''));
        if ($path !== '' && is_file(public_path($path))) {
            return asset($path);
        }

        return $category->bannerUrl();
    }

    protected function canDeleteMatches(?User $user): bool
    {
        return (bool) $user?->isSuperAdmin();
    }

    protected function canViewPlanillas(?User $user): bool
    {
        return \App\Support\WorkspaceAccess::for($user)->canViewPlanillas();
    }

    protected function canViewInscriptions(?User $user, Category $category): bool
    {
        return \App\Support\WorkspaceAccess::for($user)->canViewInscriptions($category);
    }

    protected function canReviewInscriptions(?User $user): bool
    {
        return \App\Support\WorkspaceAccess::for($user)->canReviewInscriptions();
    }

    protected function combineDateTime(Request $request, string $field): void
    {
        DateTimeInput::merge($request, $field);
    }

    protected function audit(string $action, object $model, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Operación',
            'action' => $action,
            'description' => $description,
            'auditable_type' => $model::class,
            'auditable_id' => $model->id ?? null,
            'metadata' => [
                'name' => $model->name ?? null,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
