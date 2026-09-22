<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Workspace\Concerns\ResolvesWorkspace;
use App\Models\Category;
use App\Models\Delegation;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\InscriptionQueue;
use App\Support\WorkspaceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceMobileController extends ApiController
{
    use ResolvesWorkspace;

    public function team(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        $this->assertAccessibleTeam($user, $team);

        $team->loadMissing(['category.tournament', 'delegation'])->loadCount('players');
        $category = $team->category;
        abort_unless($category, 404, 'Equipo sin categoría asignada.');

        $statusCounts = Player::query()
            ->where('team_id', $team->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->ok([
            'team' => $this->delegateTeamPayload($user, $team),
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'tournament_id' => $category->tournament_id,
                'tournament_name' => $category->tournament?->name,
            ],
            'delegation' => $team->delegation ? [
                'id' => $team->delegation->id,
                'name' => $team->delegation->name,
                'logo' => $team->delegation->logoUrl(),
            ] : null,
            'players_count' => (int) $team->players_count,
            'players_by_status' => $statusCounts,
        ]);
    }

    public function teamPlayers(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        $this->assertAccessibleTeam($user, $team);

        $team->loadMissing(['category.tournament', 'delegation']);

        $players = Player::query()
            ->with(['team.delegation', 'guardian'])
            ->where('team_id', $team->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Player $player) => $this->rosterListPlayerPayload($user, $player));

        return $this->ok([
            'team' => $this->delegateTeamPayload($user, $team),
            'players' => $players->values(),
        ]);
    }

    public function inscriptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->restrictsToAssignedClub() || $user->canAccessAdminWeb(),
            403,
            'No tenés acceso al seguimiento de fichas.'
        );

        $categoryId = $request->integer('category_id') ?: null;
        $teamId = $request->integer('team_id') ?: null;

        $categories = Category::query()
            ->when($categoryId, fn ($query) => $query->whereKey($categoryId))
            ->whereIn('id', $this->accessibleCategoryIds($user))
            ->with('tournament')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $this->canViewInscriptions($user, $category));

        abort_unless($categories->isNotEmpty(), 404, 'No encontramos categorías para seguimiento.');

        $queue = app(InscriptionQueue::class);
        $waiting = collect();
        $rejected = collect();

        foreach ($categories as $category) {
            $waiting = $waiting->merge(
                $queue->waiting($category, $user)->map(fn (Player $player) => $this->inscriptionPlayerPayload($user, $player, $category))
            );
            $rejected = $rejected->merge(
                $queue->rejected($category, $user)->map(fn (Player $player) => $this->inscriptionPlayerPayload($user, $player, $category))
            );
        }

        if ($teamId) {
            $waiting = $waiting->filter(fn (array $payload) => (int) ($payload['team_id'] ?? 0) === $teamId)->values();
            $rejected = $rejected->filter(fn (array $payload) => (int) ($payload['team_id'] ?? 0) === $teamId)->values();
        }

        $primaryCategory = $categories->first();

        return $this->ok([
            'category' => $primaryCategory ? [
                'id' => $primaryCategory->id,
                'name' => $primaryCategory->name,
                'tournament_id' => $primaryCategory->tournament_id,
                'tournament_name' => $primaryCategory->tournament?->name,
                'registrations_open' => (bool) $primaryCategory->registrationsOpen(),
                'registration_info' => $primaryCategory->workspaceValue('registration_info'),
            ] : null,
            'waiting_count' => $waiting->count(),
            'rejected_count' => $rejected->count(),
            'waiting' => $waiting->values(),
            'rejected' => $rejected->values(),
            'club_scoped' => (bool) $user->restrictsToAssignedClub(),
            'can_review' => $this->canReviewInscriptions($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function workspacePayload(User $user, ApiController $controller): array
    {
        $instance = new self;
        $teams = Team::query()
            ->accessibleTo($user)
            ->with(['category.tournament', 'delegation'])
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->map(fn (Team $team) => $instance->delegateTeamPayload($user, $team));

        $playersCount = 0;
        $portfolio = [];
        $summary = [
            'tournaments_count' => 0,
            'teams_count' => $teams->count(),
            'open_teams_count' => $teams->filter(fn (array $team) => (bool) ($team['roster_editable'] ?? false))->count(),
        ];

        if ($user->restrictsToAssignedClub()) {
            $playersCount = Player::query()
                ->whereIn('team_id', $teams->pluck('id'))
                ->count();

            $portfolio = $user->delegateClubPortfolio()
                ->map(function (array $group) use ($instance) {
                    /** @var Delegation $club */
                    $club = $group['club'];
                    $groupTeams = collect($group['teams']);
                    $openTeams = $groupTeams->filter(
                        fn (Team $team) => $team->category && $team->category->acceptsRosterEdits($team)
                    )->count();

                    return [
                        'club' => [
                            'id' => $club->id,
                            'name' => $club->name,
                            'logo' => $club->logoUrl(),
                            'origin' => $club->originLabel(),
                            'tournament_id' => $club->tournament_id,
                        ],
                        'tournament' => $group['tournament'] ? [
                            'id' => $group['tournament']->id,
                            'name' => $group['tournament']->name,
                            'slug' => $group['tournament']->slug,
                            'city' => $group['tournament']->city,
                            'country' => $group['tournament']->country,
                        ] : null,
                        'teams_count' => $groupTeams->count(),
                        'open_teams_count' => $openTeams,
                    ];
                })
                ->values()
                ->all();

            $summary['tournaments_count'] = count($portfolio);
        }

        return [
            'role' => $user->roleLabel(),
            'scope' => $user->current_scope,
            'summary' => $summary,
            'portfolio' => $portfolio,
            'teams' => $teams->values(),
            'players_count' => $playersCount,
            'capability_lines' => WorkspaceAccess::for($user)->capabilityLines(),
        ];
    }

    private function assertAccessibleTeam(User $user, Team $team): void
    {
        abort_unless(
            $user->restrictsToAssignedClub() || $user->canAccessAdminWeb(),
            403,
            'No tenés acceso a este equipo.'
        );
        abort_unless(
            Team::query()->accessibleTo($user)->whereKey($team->id)->exists(),
            403,
            'Este equipo no está dentro de tu alcance.'
        );
    }

    /**
     * @return list<int>
     */
    private function accessibleCategoryIds(User $user): array
    {
        return Team::query()
            ->accessibleTo($user)
            ->pluck('category_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function delegateTeamPayload(User $user, Team $team): array
    {
        $team->loadMissing(['category.tournament', 'delegation']);
        $payload = $this->teamPayload($team);
        $payload['category_id'] = $team->category_id;
        $payload['tournament_id'] = $team->category?->tournament_id;
        $payload['tournament_name'] = $team->category?->tournament?->name;
        $payload['delegation_id'] = $team->delegation_id;
        $payload['delegation_name'] = $team->delegation?->name;
        $payload['roster_editable'] = (bool) $team->category?->acceptsRosterEdits($team);
        $payload['registration_label'] = $team->category?->publicRegistrationLabel() ?? 'Sin categoría';
        $payload['roster_lock_reason'] = $this->rosterLockReasonFor($user, $team);
        $payload['can_manage_roster'] = $this->canManageTeamRoster($user, $team);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function rosterListPlayerPayload(User $user, Player $player): array
    {
        $player->loadMissing(['team.delegation', 'guardian']);

        return [
            'id' => $player->id,
            'name' => $player->fullName(),
            'photo' => $player->photoUrl(),
            'position' => $player->position,
            'jersey_number' => $player->jersey_number,
            'status' => $player->status,
            'status_label' => $player->statusLabel(),
            'category_id' => $player->team?->category_id,
            'team_id' => $player->team_id,
            'club' => $player->team?->delegation?->name,
            'can_edit' => $this->canEditPlayer($user, $player),
            'progress' => [
                'profile' => $player->layerOneLabel(),
                'guardian' => $player->layerTwoLabel(),
                'documents' => $player->authorizationSummary(),
                'review' => $player->reviewStatusLabel(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inscriptionPlayerPayload(User $user, Player $player, Category $category): array
    {
        $player->loadMissing(['team.delegation', 'guardian', 'documents']);

        return [
            'id' => $player->id,
            'name' => $player->fullName(),
            'photo' => $player->photoUrl(),
            'position' => $player->position,
            'jersey_number' => $player->jersey_number,
            'status' => $player->status,
            'status_label' => $player->statusLabel(),
            'category_id' => $category->id,
            'category_name' => $category->name,
            'team_id' => $player->team_id,
            'team_name' => $player->team?->name,
            'club' => $player->team?->delegation?->name,
            'updated_at' => $player->updated_at?->toIso8601String(),
            'can_edit' => $this->canEditPlayer($user, $player),
            'progress' => [
                'profile' => $player->layerOneLabel(),
                'guardian' => $player->layerTwoLabel(),
                'documents' => $player->authorizationSummary(),
                'review' => $player->reviewStatusLabel(),
            ],
        ];
    }
}
