<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\ResolvesWorkspace;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CategoryPoll;
use App\Models\ContentPost;
use App\Models\Delegation;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Models\User;
use App\Services\CompetitionBoard;
use App\Services\MatchPlanillaPresenter;
use App\Services\PlayerPerformanceService;
use App\Support\FairPlayRules;
use App\Support\CategoryWorkspace;
use App\Support\ReportPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use ResolvesWorkspace;

    public function home(Category $category, CompetitionBoard $board): View
    {
        $category = $this->assertCategory($category);
        $teams = $category->teams()
            ->with('delegation')
            ->withCount('players')
            ->orderBy('name')
            ->get();
        $workspace = $category->workspace();
        $groups = $board->standingsByGroup($category);

        $delegateTeam = auth()->user()?->rosterTeamInCategory($category);

        return view('workspace.home', $this->page($category, 'Inicio', [
            'teams' => $teams,
            'workspace' => $workspace,
            'groups' => $groups,
            'matchesCount' => $category->matches()->count(),
            'visibleColumns' => $workspace['visible_columns'],
            'columnLabels' => CategoryWorkspace::columnLabels(),
            'shortLabels' => CategoryWorkspace::columnShortLabels(),
            'highlightFirst' => (int) $workspace['highlight_first'],
            'highlightLast' => (int) $workspace['highlight_last'],
            'canEdit' => $this->canEdit(auth()->user()),
            'delegateTeam' => $delegateTeam,
            'delegateRosterLockReason' => $delegateTeam && auth()->user()?->restrictsToAssignedClub()
                ? $this->rosterLockReasonFor(auth()->user(), $delegateTeam)
                : null,
            'showDelegateBrowseNotice' => auth()->user()?->restrictsToAssignedClub() && ! $delegateTeam,
        ]));
    }

    public function standings(Request $request, Category $category, CompetitionBoard $board): View
    {
        $category = $this->assertCategory($category);
        $category->load(['teams.delegation']);

        $matches = FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'sheet', 'penaltyKicks'])
            ->where('category_id', $category->id)
            ->orderBy('scheduled_at')
            ->get();

        $workspace = $category->workspace();
        $phases = CategoryWorkspace::phaseNames($workspace);
        $existingRounds = $matches
            ->map(fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage))
            ->filter()
            ->unique()
            ->values();
        $rounds = CategoryWorkspace::roundOptions($workspace, $existingRounds->all());

        $selectedPhase = $request->string('phase', 'all')->toString();
        if ($selectedPhase === '') {
            $selectedPhase = 'all';
        }

        $selectedRound = $this->resolveGamesRoundFilter($request, $matches, $rounds);

        $visibleMatches = $this->sortVisibleMatches(
            $matches
                ->when($selectedPhase !== 'all', fn ($collection) => $collection->filter(
                    fn ($match) => CategoryWorkspace::matchPhase($match, $phases) === $selectedPhase
                ))
                ->when($selectedRound !== 'all', fn ($collection) => $collection->filter(
                    fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage) === $selectedRound
                ))
                ->values(),
            $workspace,
            $selectedPhase,
            $selectedRound
        );

        $groups = $board->standingsByGroup($category);
        $highlightFirst = (int) $workspace['highlight_first'];
        $highlightLast = (int) $workspace['highlight_last'];
        $namedGroups = $groups->keys()->reject(fn ($name) => $name === 'Sin grupo')->values();
        $groupSummary = $namedGroups->isEmpty()
            ? null
            : ($namedGroups->count() === 1
                ? $namedGroups->first()
                : 'Grupos '.$namedGroups->map(fn ($name) => preg_replace('/^Grupo\s+/u', '', (string) $name))->implode(' y '));
        $phaseLabel = $selectedPhase !== 'all' ? $selectedPhase : ($phases[0] ?? null);
        $boardSubheading = collect([$category->tournament?->name, $phaseLabel, $groupSummary])->filter()->implode(' · ');

        return view('workspace.standings', $this->page($category, 'Clasificación', [
            'groups' => $groups,
            'matches' => $matches,
            'visibleMatches' => $visibleMatches,
            'phases' => $phases,
            'rounds' => $rounds,
            'selectedPhase' => $selectedPhase,
            'selectedRound' => $selectedRound,
            'dateStats' => [
                'games' => $visibleMatches->count(),
                'goals' => $visibleMatches->sum(fn ($match) => (int) $match->home_score + (int) $match->away_score),
            ],
            'leaderboards' => [
                'Goles' => $board->rankingList($category, 'goal', 4),
                'Amarillas' => $board->rankingList($category, 'yellow', 4),
            ],
            'workspace' => $workspace,
            'visibleColumns' => $workspace['visible_columns'],
            'columnLabels' => CategoryWorkspace::columnLabels(),
            'shortLabels' => CategoryWorkspace::columnShortLabels(),
            'highlightFirst' => $highlightFirst,
            'highlightLast' => $highlightLast,
            'boardSubheading' => $boardSubheading,
            'teams' => $category->teams,
            'fields' => $this->tournamentFields($category),
            'canEdit' => $this->canEdit($request->user()),
            'defaultStart' => optional($category->tournament?->starts_at)->format('Y-m-d\T09:00') ?: now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'),
        ]));
    }

    public function printStandings(Request $request, Category $category, CompetitionBoard $board): View
    {
        $category = $this->assertCategory($category);
        $category->load(['teams.delegation', 'tournament']);

        $matches = FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam', 'field'])
            ->where('category_id', $category->id)
            ->orderBy('scheduled_at')
            ->get();

        $workspace = $category->workspace();
        $phases = CategoryWorkspace::phaseNames($workspace);
        $existingRounds = $matches
            ->map(fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage))
            ->filter()
            ->unique()
            ->values();
        $rounds = CategoryWorkspace::roundOptions($workspace, $existingRounds->all());

        $selectedPhase = $request->string('phase', 'all')->toString() ?: 'all';
        $selectedRound = $this->resolveGamesRoundFilter($request, $matches, $rounds);

        $visibleMatches = $this->sortVisibleMatches(
            $matches
                ->when($selectedPhase !== 'all', fn ($collection) => $collection->filter(
                    fn ($match) => CategoryWorkspace::matchPhase($match, $phases) === $selectedPhase
                ))
                ->when($selectedRound !== 'all', fn ($collection) => $collection->filter(
                    fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage) === $selectedRound
                ))
                ->values(),
            $workspace,
            $selectedPhase,
            $selectedRound
        );

        $groups = $board->standingsByGroup($category);
        $printScope = collect([
            $selectedPhase !== 'all' ? 'Fase '.$selectedPhase : null,
            $selectedRound !== 'all' ? $selectedRound : null,
        ])->filter()->implode(' · ') ?: 'Tabla general';

        return view('workspace.standings-print', [
            'category' => $category,
            'tournament' => $category->tournament,
            'groups' => $groups,
            'visibleMatches' => $visibleMatches,
            'selectedPhase' => $selectedPhase,
            'selectedRound' => $selectedRound,
            'printScope' => $printScope,
            'workspace' => $workspace,
            'visibleColumns' => $workspace['visible_columns'],
            'columnLabels' => CategoryWorkspace::columnLabels(),
            'shortLabels' => CategoryWorkspace::columnShortLabels(),
            'highlightFirst' => (int) $workspace['highlight_first'],
            'highlightLast' => (int) $workspace['highlight_last'],
            'canEdit' => false,
        ]);
    }

    public function brackets(Category $category, CompetitionBoard $board): View
    {
        $category = $this->assertCategory($category);

        return view('workspace.brackets', $this->page($category, 'Clasificación', [
            'brackets' => $board->brackets($category),
        ]));
    }

    public function fairPlay(Category $category, CompetitionBoard $board): View
    {
        $category = $this->assertCategory($category);

        return view('workspace.fairplay', $this->page($category, 'Clasificación', [
            'rows' => $board->fairPlay($category),
            'weights' => $category->fairPlayWeights(),
            'rules' => FairPlayRules::scaleSummary(),
            'tiebreakers' => FairPlayRules::tiebreakerLabels(),
        ]));
    }

    public function fixture(Request $request, Category $category): View
    {
        $category = $this->assertCategory($category);

        if ($request->user()->restrictsToAssignedClub()) {
            return view('workspace.fixture-public', $this->page($category, 'Fixture', [
                'fixtureImageUrl' => $this->fixtureImageUrl($category),
            ]));
        }

        $category->load(['teams.delegation']);

        $matches = FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field.venue', 'sheet'])
            ->where('category_id', $category->id)
            ->orderBy('scheduled_at')
            ->get();

        $status = $request->string('status', 'all')->toString();
        $selectedPhase = $request->string('phase', 'all')->toString();

        $workspace = $category->workspace();
        $phases = CategoryWorkspace::phaseNames($workspace);
        $existingRounds = $matches
            ->map(fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage))
            ->filter()
            ->unique()
            ->values();
        $rounds = CategoryWorkspace::roundOptions($workspace, $existingRounds->all());
        $selectedRound = $this->resolveGamesRoundFilter($request, $matches, $rounds);

        $visibleMatches = $matches
            ->when($status !== 'all', fn ($collection) => $collection->filter(function ($match) use ($status) {
                if ($status === 'finished') {
                    return in_array($match->status, ['finished', 'validated'], true);
                }

                return $match->status === $status;
            }))
            ->when($selectedPhase !== 'all', fn ($collection) => $collection->filter(
                fn ($match) => CategoryWorkspace::matchPhase($match, $phases) === $selectedPhase
            ))
            ->when($selectedRound !== 'all', fn ($collection) => $collection->filter(
                fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage) === $selectedRound
            ))
            ->sortBy(fn ($match) => $match->listingSortKey(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $conflicts = FixtureMatch::detectConflicts($matches);
        $conflictIds = $conflicts
            ->flatMap(fn (array $conflict) => [$conflict['match']->id, $conflict['other']->id])
            ->unique()
            ->values();

        $canViewPlanillas = $this->canViewPlanillas($request->user());

        return view('workspace.fixture', $this->page($category, 'Fixture', [
            'matches' => $matches,
            'visibleMatches' => $visibleMatches,
            'phases' => $phases,
            'rounds' => $rounds,
            'selectedStatus' => $status,
            'selectedPhase' => $selectedPhase,
            'selectedRound' => $selectedRound,
            'conflicts' => $canViewPlanillas ? $conflicts : collect(),
            'conflictIds' => $canViewPlanillas ? $conflictIds : collect(),
            'stats' => $canViewPlanillas ? [
                ['value' => $matches->count(), 'label' => 'Partidos', 'tone' => 'ok'],
                ['value' => $matches->where('published', true)->count(), 'label' => 'En app', 'tone' => 'ok'],
                ['value' => $matches->where('published', false)->count(), 'label' => 'Borrador', 'tone' => 'warn'],
                ['value' => $conflictIds->count(), 'label' => 'Conflictos', 'tone' => $conflictIds->isEmpty() ? 'ok' : 'warn'],
            ] : [
                ['value' => $matches->count(), 'label' => 'Partidos', 'tone' => 'ok'],
                ['value' => $matches->filter(fn ($match) => $match->isLive())->count(), 'label' => 'En juego', 'tone' => 'warn'],
                ['value' => $matches->whereIn('status', ['finished', 'validated'])->count(), 'label' => 'Finalizados', 'tone' => 'ok'],
                ['value' => $matches->whereIn('status', ['scheduled', 'rescheduled', 'live'])->count(), 'label' => 'Programados', 'tone' => 'ok'],
            ],
            'teams' => $category->teams,
            'fields' => $this->tournamentFields($category),
            'canEdit' => $this->canEdit($request->user()),
            'workspace' => $workspace,
            'columnLabels' => CategoryWorkspace::columnLabels(),
            'defaultStart' => optional($category->tournament?->starts_at)->format('Y-m-d\T09:00') ?: now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'),
        ]));
    }

    public function matchPlanilla(Request $request, Category $category, FixtureMatch $match, MatchPlanillaPresenter $presenter): View|Response
    {
        abort_unless($this->canViewPlanillas(auth()->user()), 403, 'Las planillas en blanco son solo para el staff del torneo.');
        $category = $this->assertCategory($category);
        abort_unless((int) $match->category_id === (int) $category->id, 404);

        return ReportPdf::render($request, 'workspace.planillas.blank', array_merge(
            $presenter->forMatch($match),
            [
                'category' => $category,
                'match' => $match,
            ]
        ), 'planilla-'.$match->id, 'portrait');
    }

    public function planillasHub(Request $request, Category $category): View
    {
        abort_unless($this->canViewPlanillas($request->user()), 403, 'Las planillas en blanco son solo para el staff del torneo.');
        $category = $this->assertCategory($category);
        $workspace = $category->workspace();
        $phases = CategoryWorkspace::phaseNames($workspace);
        $allMatches = FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam', 'field'])
            ->where('category_id', $category->id)
            ->orderBy('scheduled_at')
            ->get();
        $existingRounds = $allMatches
            ->map(fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage))
            ->filter()
            ->unique()
            ->values();
        $rounds = CategoryWorkspace::roundOptions($workspace, $existingRounds->all());

        $selectedPhase = $request->string('phase', 'all')->toString();
        if ($selectedPhase === '') {
            $selectedPhase = 'all';
        }
        $selectedRound = $this->resolvePlanillaRoundFilter($request);
        $selectedStatus = $request->string('status', 'all')->toString();

        $matches = $this->filteredCategoryMatches($request, $category, $selectedStatus);

        return view('workspace.planillas.index', $this->page($category, 'Fixture', [
            'matches' => $matches,
            'totalMatches' => $allMatches->count(),
            'phases' => $phases,
            'rounds' => $rounds,
            'selectedPhase' => $selectedPhase,
            'selectedRound' => $selectedRound,
            'selectedStatus' => $selectedStatus,
        ]));
    }

    public function planillasDownload(Request $request, Category $category, MatchPlanillaPresenter $presenter): View
    {
        abort_unless($this->canViewPlanillas($request->user()), 403, 'Las planillas en blanco son solo para el staff del torneo.');
        $category = $this->assertCategory($category);
        $matches = $this->filteredCategoryMatches($request, $category, $request->string('status', 'all')->toString());
        $planillas = $matches->map(fn (FixtureMatch $match) => $presenter->forMatch($match));

        return view('workspace.planillas.bulk', [
            'category' => $category,
            'planillas' => $planillas,
            'count' => $planillas->count(),
        ]);
    }

    public function rankings(Category $category, CompetitionBoard $board): View
    {
        $category = $this->assertCategory($category);
        $user = auth()->user();
        $canEdit = $this->canEdit($user);
        $voterKey = CategoryPoll::voterKey(request());

        $pollsQuery = CategoryPoll::query()
            ->where('category_id', $category->id)
            ->with(['options'])
            ->withCount('votes');

        if (! $canEdit) {
            $pollsQuery->where('is_visible', true);
        }

        $polls = $pollsQuery
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (CategoryPoll $poll) use ($voterKey) {
                $poll->setAttribute('has_voted', $poll->hasVoted($voterKey));
                $poll->setAttribute('results', ($poll->show_results || $poll->has_voted) ? $poll->resultBreakdown() : null);
                $poll->setAttribute('voter_count', $poll->totalVotes());
            });

        return view('workspace.rankings', $this->page($category, 'Rankings', [
            'rankings' => $board->playerRankings($category),
            'leastGoals' => $board->leastGoalsAgainst($category)->take(12),
            'polls' => $polls,
            'canEdit' => $canEdit,
            'voterKey' => $voterKey,
        ]));
    }

    public function media(Category $category): View
    {
        $category = $this->assertCategory($category);
        $posts = ContentPost::query()
            ->where('category_id', $category->id)
            ->latest()
            ->get();

        return view('workspace.media', $this->page($category, 'Media', [
            'posts' => $posts,
            'canEdit' => $this->canEdit(auth()->user()),
        ]));
    }

    public function settings(Request $request, Category $category): View
    {
        abort_unless($this->canEdit($request->user()), 403, 'No podés configurar esta categoría.');

        $category = $this->assertCategory($category);
        $category->load(['teams' => fn ($query) => $query->with('delegation')->withCount('players')]);

        $playerQuery = Player::query()->whereHas('team', fn ($query) => $query->where('category_id', $category->id));
        $playerCount = (clone $playerQuery)->count();
        $pendingPlayers = (clone $playerQuery)->whereIn('status', ['pending', 'observed', 'submitted', 'awaiting_guardian'])->count();
        $pendingDocs = PlayerDocument::query()
            ->whereHas('player.team', fn ($query) => $query->where('category_id', $category->id))
            ->whereIn('status', ['pending', 'observed'])
            ->count();

        $fields = $this->tournamentFields($category);

        return view('workspace.settings', $this->page($category, 'Configuración', [
            'workspace' => $category->workspace(),
            'columnLabels' => CategoryWorkspace::columnLabels(),
            'playerCount' => $playerCount,
            'pendingPlayers' => $pendingPlayers,
            'pendingDocs' => $pendingDocs,
            'fields' => $fields,
            'clubCount' => Delegation::query()->where('tournament_id', $category->tournament_id)->count(),
            'bannerLogos' => $category->bannerLogoChoices(),
            'canEdit' => $this->canEdit($request->user()),
            'canManageUsers' => (bool) $request->user()?->hasPermission('users.manage'),
            'modalities' => Category::modalities(),
            'competitionFormats' => Category::competitionFormats(),
            'statusLabels' => Category::statusLabels(),
        ]));
    }

    public function teams(Category $category): View
    {
        $category = $this->assertCategory($category);
        $this->linkTournamentClubs($category);
        $teams = $category->teams()
            ->withCount('players')
            ->with(['staffMembers', 'delegation'])
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();

        return view('workspace.teams', $this->page($category, 'Equipos', [
            'teams' => $teams,
            'clubs' => $this->tournamentClubs($category),
            'canEdit' => $this->canEdit(auth()->user()),
        ]));
    }

    public function players(Request $request, Category $category): View|RedirectResponse
    {
        $category = $this->assertCategory($category);
        $user = $request->user();
        $teams = $category->teams()
            ->accessibleTo($user)
            ->with('delegation')
            ->withCount('players')
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();
        $teamId = $request->integer('team_id');
        $selectedTeam = $teamId > 0 ? $teams->firstWhere('id', $teamId) : null;

        $players = Player::query()
            ->with(['team.delegation', 'documents'])
            ->whereHas('team', function ($query) use ($category, $user) {
                $query->where('category_id', $category->id)->accessibleTo($user);
            })
            ->when($selectedTeam, fn ($query) => $query->where('team_id', $selectedTeam->id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $managedTeam = $user?->rosterTeamInCategory($category);

        if ($managedTeam && $teamId === 0 && $user?->restrictsToAssignedClub()) {
            return redirect()->route('workspace.categories.players', [
                $category,
                'team_id' => $managedTeam->id,
            ]);
        }

        return view('workspace.players', $this->page($category, 'Jugadores', [
            'teams' => $teams,
            'players' => $players,
            'selectedTeam' => $selectedTeam,
            'canManageRoster' => $selectedTeam
                ? $this->canManageTeamRoster($user, $selectedTeam)
                : false,
            'canEdit' => false,
            'managedTeamId' => $managedTeam?->id,
            'rosterLockReason' => $managedTeam
                ? $this->rosterLockReasonFor($user, $managedTeam)
                : null,
        ]));
    }

    public function player(Category $category, Player $player, PlayerPerformanceService $performance): View
    {
        $category = $this->assertCategory($category);
        abort_unless((int) $player->team?->category_id === (int) $category->id, 404);
        abort_unless($this->canViewPlayer(auth()->user(), $category, $player), 403, 'Este jugador no está dentro de tu alcance.');

        $canEdit = $this->canEditPlayer(auth()->user(), $player);
        $player->load(['team.delegation', 'team.tournament']);
        $ownsPlayer = ! auth()->user()?->restrictsToAssignedClub()
            || Team::query()->accessibleTo(auth()->user())->whereKey($player->team_id)->exists();
        $canViewFullFicha = $canEdit || $ownsPlayer;

        if ($canViewFullFicha) {
            $player->load(['guardian', 'documents', 'invitations']);
        }

        return view('workspace.player-view', $this->page($category, 'Jugadores', [
            'player' => $player,
            'canEdit' => $canEdit,
            'canViewFullFicha' => $canViewFullFicha,
            'stats' => $performance->summary($player),
        ]));
    }

    public function playerEdit(Category $category, Player $player): View
    {
        $category = $this->assertCategory($category);
        abort_unless((int) $player->team?->category_id === (int) $category->id, 404);
        abort_unless($this->canViewPlayer(auth()->user(), $category, $player), 403, 'Este jugador no está dentro de tu alcance.');
        abort_unless($this->canEditPlayer(auth()->user(), $player), 403, 'No podés editar esta ficha.');

        $player->load(['team', 'guardian', 'documents', 'invitations']);
        $player->ensureDocuments();

        return view('workspace.player-edit', $this->page($category, 'Jugadores', [
            'player' => $player,
            'statuses' => Player::statusLabels(),
            'canManageRoster' => $this->canManageTeamRoster(auth()->user(), $player->team),
            'rosterLockReason' => $this->rosterLockReasonFor(auth()->user(), $player->team),
        ]));
    }

    public function team(Category $category, Team $team): View
    {
        $category = $this->assertCategory($category);
        abort_unless((int) $team->category_id === (int) $category->id, 404);
        abort_unless($this->canViewTeam(auth()->user(), $category, $team), 403, 'Este equipo no está dentro de tu alcance.');
        $canManageRoster = $this->canManageTeamRoster(auth()->user(), $team);
        $team->load([
            'staffMembers',
            'players' => fn ($query) => $query
                ->when($canManageRoster, fn ($players) => $players->with('guardian'))
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'delegation',
        ])->loadCount('players');

        return view('workspace.team', $this->page($category, 'Equipos', [
            'team' => $team,
            'clubs' => $this->tournamentClubs($category),
            'staffRoles' => TeamStaff::roleLabels(),
            'playerStatuses' => Player::statusLabels(),
            'canEdit' => $this->canEditTeam(auth()->user(), $team),
            'canManageRoster' => $canManageRoster,
            'canShareRoster' => $this->canShareRosterLink(auth()->user(), $team),
            'rosterLockReason' => $this->rosterLockReasonFor(auth()->user(), $team),
            'rosterLink' => app(\App\Services\RosterShareService::class)->activeForTeam($team),
        ]));
    }

    public function match(Category $category, FixtureMatch $match): View
    {
        $category = $this->assertCategory($category);
        abort_unless((int) $match->category_id === (int) $category->id, 404);
        $match->load([
            'homeTeam.delegation',
            'awayTeam.delegation',
            'homeTeam.players' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name'),
            'awayTeam.players' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name'),
            'homeTeam.staffMembers' => fn ($query) => $query->where('status', 'active')->orderBy('last_name')->orderBy('first_name'),
            'awayTeam.staffMembers' => fn ($query) => $query->where('status', 'active')->orderBy('last_name')->orderBy('first_name'),
            'field',
            'sheet.events.player.documents',
            'sheet.events.teamStaff',
            'sheet.events.team.delegation',
            'sheet.incidents',
        ]);

        return view('workspace.match', $this->page($category, 'Clasificación', [
            'match' => $match,
            'sheet' => $match->sheet,
            'fields' => $this->tournamentFields($category),
            'canEdit' => $this->canEdit(auth()->user()),
        ]));
    }

    public function documents(Category $category): View
    {
        $this->assertCategoryManagement(auth()->user());
        $category = $this->assertCategory($category);
        $documents = PlayerDocument::query()
            ->with('player.team')
            ->whereHas('player.team', fn ($query) => $query->where('category_id', $category->id))
            ->whereIn('type', Player::documentTypes())
            ->latest()
            ->get();

        return view('workspace.documents', $this->page($category, 'Configuración', [
            'documents' => $documents,
            'canEdit' => $this->canEdit(auth()->user()),
        ]));
    }

    public function inscriptions(Category $category): View
    {
        $user = auth()->user();
        $category = $this->assertCategory($category);
        abort_unless($this->canViewInscriptions($user, $category), 403);

        $queue = app(\App\Services\InscriptionQueue::class);
        $pending = $queue->waiting($category, $user)->each(function (Player $player) use ($user) {
            $player->setAttribute('can_edit_ficha', $this->canEditPlayer($user, $player));
        });
        $rejected = $queue->rejected($category, $user)->each(function (Player $player) use ($user) {
            $player->setAttribute('can_edit_ficha', $this->canEditPlayer($user, $player));
        });

        return view('workspace.inscriptions', $this->page($category, 'Inscripciones', [
            'pending' => $pending,
            'rejected' => $rejected,
            'waitingCount' => $queue->waitingCount($category, $user),
            'rejectedCount' => $queue->rejectedCount($category, $user),
            'workspace' => $category->workspace(),
            'columnLabels' => CategoryWorkspace::columnLabels(),
            'canEdit' => $this->canEdit($user),
            'canReview' => $this->canReviewInscriptions($user),
            'clubScoped' => (bool) $user?->restrictsToAssignedClub(),
        ]));
    }

    public function clubs(Category $category): View|RedirectResponse
    {
        $category = $this->assertCategory($category);

        if (auth()->user()?->restrictsToAssignedClub()) {
            return redirect()->route('workspace.my-club', ['tournament' => $category->tournament_id]);
        }

        $this->linkTournamentClubs($category);
        $clubs = Delegation::query()
            ->accessibleTo(auth()->user())
            ->where('tournament_id', $category->tournament_id)
            ->with(['delegates.roles'])
            ->withCount(['teams', 'delegates'])
            ->orderBy('name')
            ->get();

        return view('workspace.clubs', $this->page($category, 'Delegaciones', [
            'clubs' => $clubs,
            'categories' => $category->tournament->categories()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'canEdit' => $this->canEdit(auth()->user()),
            'canManageClubs' => $this->canManageClubs(auth()->user()),
            'canAssignDelegates' => $this->canAssignDelegates(auth()->user()),
        ]));
    }

    public function club(Category $category, Delegation $delegation): View|RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertClubVisible(auth()->user(), $delegation);
        abort_unless((int) $delegation->tournament_id === (int) $category->tournament_id, 404);

        if (auth()->user()?->restrictsToAssignedClub()) {
            return redirect()->route('workspace.my-club', ['tournament' => $category->tournament_id]);
        }

        $delegation->load([
            'teams' => fn ($query) => $query->with('category.tournament')->orderBy('category_id')->orderBy('name'),
            'delegates.roles',
        ])->loadCount(['teams', 'delegates']);

        $delegateUsers = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasRole('delegado') && $user->canAccessTournament((int) $category->tournament_id))
            ->values();

        if (auth()->user()?->restrictsToAssignedClub()) {
            $delegateUsers = $delegateUsers->where('id', auth()->id())->values();
        }

        return view('workspace.club', $this->page($category, 'Delegaciones', [
            'club' => $delegation,
            'categories' => $category->tournament->categories()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'delegateUsers' => $delegateUsers,
            'assignedDelegates' => $delegation->credentialDelegates(),
            'assignedDelegate' => $delegation->principalDelegate(),
            'canEdit' => $this->canEdit(auth()->user()),
            'canManageClubs' => $this->canManageClubs(auth()->user()),
            'canAssignDelegates' => $this->canAssignDelegates(auth()->user()),
        ]));
    }

    public function people(Category $category): View
    {
        $this->assertCategoryManagement(auth()->user());
        $category = $this->assertCategory($category);
        $people = User::query()
            ->with(['roles', 'delegation'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->canAccessTournament((int) $category->tournament_id))
            ->values();

        return view('workspace.people', $this->page($category, 'Configuración', [
            'people' => $people,
            'clubs' => $this->tournamentClubs($category),
            'roles' => Role::query()->where('slug', '!=', 'super-admin')->orderBy('name')->get(),
            'canEdit' => $this->canEdit(auth()->user()),
            'canManageUsers' => (bool) auth()->user()?->hasPermission('users.manage'),
        ]));
    }

    public function history(Category $category): View
    {
        $this->assertCategoryManagement(auth()->user());
        $category = $this->assertCategory($category);
        $logs = AuditLog::query()
            ->with('user')
            ->where('module', 'Operaci?n')
            ->latest('created_at')
            ->limit(80)
            ->get();

        return view('workspace.history', $this->page($category, 'Configuración', [
            'logs' => $logs,
        ]));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Field>
     */
    private function tournamentFields(Category $category)
    {
        return Field::query()
            ->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))
            ->with('venue')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Delegation>
     */
    private function tournamentClubs(Category $category)
    {
        return Delegation::query()
            ->accessibleTo(auth()->user())
            ->where('tournament_id', $category->tournament_id)
            ->orderBy('name')
            ->get();
    }

    private function linkTournamentClubs(Category $category): void
    {
        Delegation::query()
            ->where('tournament_id', $category->tournament_id)
            ->get()
            ->each(fn (Delegation $club) => $club->claimOrphanTeams());
    }

    /**
     * @return \Illuminate\Support\Collection<int, FixtureMatch>
     */
    private function filteredCategoryMatches(Request $request, Category $category, ?string $forcedStatus = null)
    {
        $matches = FixtureMatch::query()
            ->with(['homeTeam.players', 'awayTeam.players', 'homeTeam.staffMembers', 'awayTeam.staffMembers', 'field.venue', 'tournament', 'category', 'sheet'])
            ->where('category_id', $category->id)
            ->orderBy('scheduled_at')
            ->get();

        $selectedPhase = $request->string('phase', 'all')->toString();
        if ($selectedPhase === '') {
            $selectedPhase = 'all';
        }
        $selectedRound = $this->resolvePlanillaRoundFilter($request);
        $selectedStatus = $forcedStatus ?? $request->string('status', 'all')->toString();
        $workspace = $category->workspace();
        $phases = CategoryWorkspace::phaseNames($workspace);
        $selectedIds = collect($request->input('matches', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($selectedIds !== []) {
            return $matches
                ->whereIn('id', $selectedIds)
                ->sortBy(fn ($match) => $match->listingSortKey(), SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        }

        return $matches
            ->when($selectedStatus === 'pending', fn ($collection) => $collection->filter(
                fn ($match) => $this->matchIsPendingForPlanilla($match)
            ))
            ->when($selectedStatus === 'scheduled', fn ($collection) => $collection->filter(
                fn ($match) => $this->matchIsScheduledForPlanilla($match)
            ))
            ->when($selectedStatus === 'finished', fn ($collection) => $collection->filter(
                fn ($match) => in_array($match->status, ['finished', 'validated'], true)
            ))
            ->when($selectedPhase !== 'all', fn ($collection) => $collection->filter(
                fn ($match) => CategoryWorkspace::matchPhase($match, $phases) === $selectedPhase
            ))
            ->when($selectedRound !== 'all', fn ($collection) => $collection->filter(
                fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage) === $selectedRound
            ))
            ->sortBy(fn ($match) => $match->listingSortKey(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function resolvePlanillaRoundFilter(Request $request): string
    {
        $round = $request->string('round', $request->string('date', 'all')->toString())->toString();

        if ($round === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $round)) {
            return 'all';
        }

        return $round;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FixtureMatch>  $matches
     * @return \Illuminate\Support\Collection<int, FixtureMatch>
     */
    private function sortVisibleMatches($matches, array $workspace, string $phase, string $round)
    {
        $scope = CategoryWorkspace::matchOrderScope(
            $phase !== 'all' ? $phase : null,
            $round !== 'all' ? $round : null
        );
        $ordered = CategoryWorkspace::applyMatchDisplayOrder($matches, $workspace, $scope);

        if (($workspace['match_order'][$scope] ?? []) !== []) {
            return $ordered->values();
        }

        return $matches
            ->sortBy(fn ($match) => $match->listingSortKey(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Sin filtro explícito: muestra la fecha del próximo partido (o la última jugada).
     * Si el usuario elige "Todas", respeta round=all.
     *
     * @param  \Illuminate\Support\Collection<int, FixtureMatch>  $matches
     * @param  list<string>  $rounds
     */
    private function resolveGamesRoundFilter(Request $request, $matches, array $rounds): string
    {
        if ($request->has('round') || $request->has('date')) {
            $round = $request->string('round', $request->string('date', 'all')->toString())->toString();

            if ($round === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $round)) {
                return 'all';
            }

            return $round;
        }

        return $this->upcomingRoundForMatches($matches, $rounds);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FixtureMatch>  $matches
     * @param  list<string>  $rounds
     */
    private function upcomingRoundForMatches($matches, array $rounds): string
    {
        if ($rounds === []) {
            return 'all';
        }

        $pending = $matches
            ->filter(fn (FixtureMatch $match) => ! in_array($match->status, ['finished', 'validated'], true))
            ->sortBy(function (FixtureMatch $match) {
                $time = $match->scheduled_at?->getTimestamp() ?? PHP_INT_MAX;

                return sprintf('%015d-%s', $time, CategoryWorkspace::normalizeRound($match->round, $match->stage));
            })
            ->values();

        if ($pending->isNotEmpty()) {
            $round = CategoryWorkspace::normalizeRound($pending->first()->round, $pending->first()->stage);

            return $round !== '' ? $round : ($rounds[0] ?? 'all');
        }

        foreach (array_reverse($rounds) as $round) {
            $hasMatches = $matches->contains(
                fn (FixtureMatch $match) => CategoryWorkspace::normalizeRound($match->round, $match->stage) === $round
            );

            if ($hasMatches) {
                return $round;
            }
        }

        return $rounds[0] ?? 'all';
    }

    private function matchIsPendingForPlanilla(FixtureMatch $match): bool
    {
        return ! in_array($match->status, ['finished', 'validated'], true);
    }

    private function matchIsScheduledForPlanilla(FixtureMatch $match): bool
    {
        $status = trim((string) $match->status);

        if ($status === '') {
            return true;
        }

        return in_array($status, ['scheduled', 'rescheduled'], true);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function page(Category $category, string $active, array $extra = []): array
    {
        return array_merge([
            'category' => $category,
            'tournament' => $category->tournament,
            'active' => $active,
            'title' => $category->name.' ? '.$active,
            'canDeleteMatches' => $this->canDeleteMatches(auth()->user()),
            'canScheduleMatches' => $this->canScheduleMatches(auth()->user()),
            'canOperateMatch' => $this->canOperateMatch(auth()->user()),
            'canViewPlanillas' => $this->canViewPlanillas(auth()->user()),
        ], $extra);
    }
}
