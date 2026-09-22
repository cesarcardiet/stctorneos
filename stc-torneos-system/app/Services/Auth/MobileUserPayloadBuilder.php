<?php

namespace App\Services\Auth;

use App\Models\Team;
use App\Models\User;
use App\Support\InvitationScope;
use App\Support\WorkspaceAccess;

class MobileUserPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $user->loadMissing(['roles.permissions', 'tournament', 'delegation', 'player.team.category', 'favoriteTeams.team']);

        $roles = $user->roles->map(fn ($role) => [
            'slug' => $role->slug,
            'name' => $role->name,
            'scope_type' => InvitationScope::normalize($role->pivot->scope_type ?? null),
            'scope_id' => $role->pivot->scope_id ? (int) $role->pivot->scope_id : null,
        ])->values()->all();

        $permissions = $user->permissionSlugs()->values()->all();
        $tournaments = $user->accessibleTournaments()->map(fn ($tournament) => [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'slug' => $tournament->slug,
            'city' => $tournament->city,
            'country' => $tournament->country,
        ])->values()->all();

        $delegations = collect($user->scopedDelegationIds())
            ->map(function (int $id) {
                $club = \App\Models\Delegation::query()->with('tournament')->find($id);

                return $club ? [
                    'id' => $club->id,
                    'name' => $club->name,
                    'tournament_id' => $club->tournament_id,
                    'tournament_name' => $club->tournament?->name,
                ] : null;
            })
            ->filter()
            ->values()
            ->all();

        $category = $user->playerCategory();
        $player = $user->player;
        $favoriteTeam = $user->favoriteTeams->first()?->team;
        $assignedCategories = $user->restrictsToAssignedClub()
            ? Team::query()
                ->accessibleTo($user)
                ->with('category')
                ->get()
                ->pluck('category')
                ->filter()
                ->unique('id')
                ->map(fn ($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'tournament_id' => $cat->tournament_id,
                ])
                ->values()
                ->all()
            : [];
        $tutorPlayers = $user->isTutorAccount()
            ? $user->tutorPlayers()->loadMissing(['team.category', 'team.delegation', 'guardian'])
            : collect();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'role' => $user->roleLabel(),
            'role_slug' => $user->primaryRole()?->slug,
            'scope' => $user->current_scope,
            'roles' => $roles,
            'permissions' => $permissions,
            'capabilities' => $user->capabilities(),
            'capability_lines' => WorkspaceAccess::for($user)->capabilityLines(),
            'favorite_team' => $favoriteTeam ? [
                'id' => $favoriteTeam->id,
                'name' => $favoriteTeam->name,
                'flag' => $favoriteTeam->flagUrl(),
            ] : null,
            'assigned_categories' => $assignedCategories,
            'context' => [
                'label' => $user->current_scope,
                'tournament_ids' => collect($user->assignedTournamentIds())->values()->all(),
                'delegation_ids' => $user->scopedDelegationIds(),
                'player_id' => $user->player_id,
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name,
                    'tournament_id' => $category->tournament_id,
                ] : null,
            ],
            'player' => $player ? [
                'id' => $player->id,
                'name' => $player->fullName(),
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'position' => $player->position,
                'jersey' => $player->jersey_number,
                'nationality' => $player->nationality,
                'birth_year' => $player->birth_date?->format('Y'),
                'age' => $player->age(),
                'photo' => $player->photoUrl(),
                'club' => $player->team?->delegation?->name,
                'category' => $player->team?->category?->name,
                'preferred_foot' => $player->preferred_foot,
                'height' => $player->height,
                'notes' => $player->notes,
            ] : null,
            'tutor_players' => $tutorPlayers->map(fn ($linked) => [
                'id' => $linked->id,
                'name' => $linked->fullName(),
                'photo' => $linked->photoUrl(),
                'category' => $linked->team?->category?->name,
                'club' => $linked->team?->delegation?->name,
                'relationship' => $linked->guardian?->relationship,
                'linked' => true,
            ])->values()->all(),
            'tournaments' => $tournaments,
            'delegations' => $delegations,
            'navigation' => $this->navigation($user),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, enabled: bool, route: ?string}>
     */
    private function navigation(User $user): array
    {
        $items = [
            $this->navItem('home', 'Inicio', true, $user),
            $this->navItem('favorites', 'Favoritos', true, $user),
            $this->navItem('notifications', 'Notificaciones', true, $user),
        ];

        if ($user->isPlayerAccount()) {
            $items[] = $this->navItem('player_portal', 'Mi ficha', true, $user);
        }

        if ($user->isTutorAccount()) {
            $items[] = $this->navItem('tutor_portal', 'Mis jugadores', true, $user);
            $items[] = $this->navItem('tutor_ficha', 'Completar ficha', true, $user);
        }

        if ($user->restrictsToAssignedClub()) {
            $items[] = $this->navItem('workspace', 'Mi delegación', true, $user);
            $items[] = $this->navItem('delegation_teams', 'Mis equipos', true, $user);
            $items[] = $this->navItem('delegation_roster', 'Lista de buena fe', true, $user);
            $items[] = $this->navItem('delegation_inscriptions', 'Seguimiento fichas', true, $user);
            if ($user->hasAnyPermission('delegations.manage', 'players.approve', 'tournaments.manage')) {
                $items[] = $this->navItem('new_player', 'Nuevo jugador', true, $user);
            }
        } elseif ($user->isMatchStaffOnly()) {
            $items[] = $this->navItem('staff_matches', 'Partidos de hoy', true, $user);
            $items[] = $this->navItem('staff_live', 'En vivo', true, $user);
            if ($user->hasAnyPermission('matches.manage', 'match_sheets.manage')) {
                $items[] = $this->navItem('staff_sheet', 'Planillas', true, $user);
            }
        } elseif ($user->canAccessAdminWeb()) {
            $items[] = $this->navItem('staff_matches', 'Partidos staff', true, $user);
            $items[] = $this->navItem('admin_web', 'Administración web', false, $user);
        }

        return $items;
    }

    /**
     * @return array{key: string, label: string, enabled: bool, route: ?string}
     */
    private function navItem(string $key, string $label, bool $enabled, User $user): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'enabled' => $enabled,
            'route' => $this->navigationRoute($key, $user),
        ];
    }

    private function navigationRoute(string $key, User $user): ?string
    {
        return match ($key) {
            'home' => '/home',
            'favorites' => '/favorites',
            'notifications' => '/notifications',
            'player_portal' => '/player/portal',
            'tutor_portal' => '/tutor/players',
            'tutor_ficha' => $this->tutorFichaRoute($user),
            'workspace' => '/workspace',
            'delegation_teams' => '/workspace/teams',
            'delegation_roster' => '/workspace/roster',
            'delegation_inscriptions' => '/workspace/inscriptions',
            'new_player' => '/players/new',
            'matches' => '/live',
            'staff_matches' => '/staff/matches',
            'staff_live' => '/staff/matches?scope=live',
            'staff_sheet' => '/staff/matches?scope=mine',
            default => null,
        };
    }

    private function tutorFichaRoute(User $user): string
    {
        $player = $user->tutorPlayers()->first();

        return $player
            ? '/tutor/players/'.$player->id.'/ficha'
            : '/tutor/players';
    }
}
