<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Delegation;
use App\Models\FixtureMatch;
use App\Models\MatchSheet;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->filled('search')) {
            return redirect()->route('admin.search', ['search' => $request->string('search')->toString()]);
        }

        $user = $request->user();
        $availableTournaments = $user->accessibleTournaments();
        $canSeeAllTournaments = $user->canAccessAllTournaments();
        $tournament = $this->resolveTournament($request, $user);
        $tournamentQuery = array_filter(['tournament_id' => $tournament?->id]);

        $scopeMatches = fn (Builder $query) => $query->when(
            $tournament,
            fn (Builder $query) => $query->where('tournament_id', $tournament->id),
            fn (Builder $query) => $query->when(
                ! $canSeeAllTournaments,
                fn (Builder $query) => $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0])
            )
        );

        $liveMatches = FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam', 'field'])
            ->tap($scopeMatches)
            ->where('status', 'live')
            ->orderBy('scheduled_at')
            ->get();

        [$scheduleDate, $scheduleHeading] = $this->scheduleWindow($tournament, $user);

        $todaySchedule = FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam', 'field', 'category'])
            ->tap($scopeMatches)
            ->whereDate('scheduled_at', $scheduleDate)
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get();

        $scheduleCount = FixtureMatch::query()
            ->tap($scopeMatches)
            ->whereDate('scheduled_at', $scheduleDate)
            ->count();

        $playerQuery = Player::query()->when(
            $tournament,
            fn (Builder $query) => $query->whereHas('team', fn (Builder $team) => $team->where('tournament_id', $tournament->id)),
            fn (Builder $query) => $query->when(
                ! $canSeeAllTournaments,
                fn (Builder $query) => $query->whereHas('team', fn (Builder $team) => $team->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]))
            )
        );

        $documentQuery = PlayerDocument::query()->when(
            $tournament,
            fn (Builder $query) => $query->whereHas('player.team', fn (Builder $team) => $team->where('tournament_id', $tournament->id)),
            fn (Builder $query) => $query->when(
                ! $canSeeAllTournaments,
                fn (Builder $query) => $query->whereHas('player.team', fn (Builder $team) => $team->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]))
            )
        );

        $activeCategories = Category::query()
            ->tap($scopeMatches)
            ->where('status', 'active')
            ->count();

        $delegations = Delegation::query()->tap($scopeMatches)->count();
        $teams = Team::query()->tap($scopeMatches)->count();

        $registeredPlayers = (clone $playerQuery)->count();
        $enabledPlayers = (clone $playerQuery)->where('status', 'enabled')->count();
        $pendingFiles = (clone $playerQuery)->whereIn('status', ['draft', 'pending', 'observed'])->count();
        $pendingDocs = (clone $documentQuery)->whereIn('status', ['pending', 'observed'])->count();

        $liveCount = $liveMatches->count();
        $finishedCount = FixtureMatch::query()
            ->tap($scopeMatches)
            ->whereIn('status', ['finished', 'validated'])
            ->count();

        $link = fn (string $route, array $params = [], string ...$permissions) => $this->actionUrl($user, $route, $params, ...$permissions);

        $upcomingJornadas = FixtureMatch::query()
            ->tap($scopeMatches)
            ->where('scheduled_at', '>', $scheduleDate->copy()->endOfDay())
            ->orderBy('scheduled_at')
            ->limit(80)
            ->get(['scheduled_at'])
            ->groupBy(fn (FixtureMatch $match) => $match->scheduled_at?->toDateString())
            ->map(fn ($matches, $date) => [
                'date' => Carbon::parse((string) $date),
                'count' => $matches->count(),
                'url' => $link('admin.fixture.index', array_merge($tournamentQuery, ['date' => (string) $date]), 'matches.manage'),
            ])
            ->take(4)
            ->values();

        $stats = [
            ['Categorías activas', $activeCategories, 'yellow', $link('admin.categories.index', array_merge($tournamentQuery, ['status' => 'active']), 'tournaments.manage')],
            ['Delegaciones', $delegations, 'blue', $link('admin.delegations.index', $tournamentQuery, 'delegations.manage')],
            ['Equipos', $teams, 'cyan', $link('admin.teams.index', $tournamentQuery, 'delegations.manage')],
            ['Jugadores registrados', $registeredPlayers, 'green', $link('admin.players.index', $tournamentQuery, 'players.approve')],
            ['Jugadores habilitados', $enabledPlayers, 'green', $link('admin.players.index', array_merge($tournamentQuery, ['status' => 'enabled']), 'players.approve')],
            ['Fichas pendientes', $pendingFiles, 'yellow', $link('admin.players.index', array_merge($tournamentQuery, ['status' => 'review']), 'players.approve')],
            ['Documentación pendiente', $pendingDocs, 'yellow', $link('admin.documents.index', array_merge($tournamentQuery, ['status' => 'pending']), 'players.approve')],
            [$scheduleDate->isToday() ? 'Partidos del día' : 'Partidos jornada', $scheduleCount, 'cyan', $link('admin.fixture.index', array_merge($tournamentQuery, ['date' => $scheduleDate->toDateString()]), 'matches.manage')],
            ['Partidos en vivo', $liveCount, 'green', $link('admin.fixture.index', array_merge($tournamentQuery, ['status' => 'live']), 'matches.manage')],
            ['Partidos finalizados', $finishedCount, 'blue', $link('admin.fixture.index', array_merge($tournamentQuery, ['status' => 'finished']), 'matches.manage')],
            ['Próximas jornadas', $upcomingJornadas->count(), 'cyan', $link('admin.fixture.index', $tournamentQuery, 'matches.manage')],
        ];

        $observedSheets = MatchSheet::query()
            ->when($tournament, fn (Builder $query) => $query->whereHas('match', fn (Builder $match) => $match->where('tournament_id', $tournament->id)))
            ->when(
                ! $tournament && ! $canSeeAllTournaments,
                fn (Builder $query) => $query->whereHas('match', fn (Builder $match) => $match->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]))
            )
            ->where('status', 'observed')
            ->count();
        $scheduledNotices = AppNotification::query()->where('status', 'scheduled')->count();

        $alerts = [];

        if ($pendingFiles > 0) {
            $alerts[] = ['warn', $pendingFiles.' fichas pendientes de revisión', $link('admin.players.index', array_merge($tournamentQuery, ['status' => 'review']), 'players.approve')];
        }

        if ($pendingDocs > 0) {
            $alerts[] = ['warn', $pendingDocs.' documentos pendientes', $link('admin.documents.index', array_merge($tournamentQuery, ['status' => 'pending']), 'players.approve')];
        }

        if ($observedSheets > 0) {
            $alerts[] = ['danger', $observedSheets.' planillas observadas', $link('admin.sheets.index', [], 'match_sheets.manage')];
        }

        if ($scheduledNotices > 0) {
            $alerts[] = ['', $scheduledNotices.' notificaciones programadas', $link('admin.communications.notifications', [], 'communications.manage')];
        }

        if ($alerts === []) {
            $alerts[] = ['ok', 'No hay tareas pendientes', null];
        }

        $liveActionUrl = $liveCount === 1
            ? $link('admin.fixture.show', ['match' => $liveMatches->first()], 'matches.manage')
            : $link('admin.fixture.index', array_merge($tournamentQuery, ['status' => 'live']), 'matches.manage');

        $actions = array_values(array_filter([
            [
                'label' => 'Fichas pendientes',
                'hint' => $pendingFiles.' en revisión',
                'url' => $link('admin.players.index', array_merge($tournamentQuery, ['status' => 'review']), 'players.approve'),
            ],
            [
                'label' => 'Documentación pendiente',
                'hint' => $pendingDocs.' por revisar',
                'url' => $link('admin.documents.index', array_merge($tournamentQuery, ['status' => 'pending']), 'players.approve'),
            ],
            [
                'label' => 'Partido en vivo',
                'hint' => $liveCount > 0 ? $liveCount.' en juego' : 'Ninguno ahora',
                'url' => $liveActionUrl,
            ],
            [
                'label' => 'Jornada',
                'hint' => $scheduleHeading,
                'url' => $link('admin.fixture.index', array_merge($tournamentQuery, ['date' => $scheduleDate->toDateString()]), 'matches.manage'),
            ],
            [
                'label' => 'Fixture',
                'hint' => 'Calendario completo',
                'url' => $link('admin.fixture.index', $tournamentQuery, 'matches.manage'),
            ],
            [
                'label' => 'Resultados',
                'hint' => $finishedCount.' oficiales',
                'url' => $link('admin.results.index', $tournamentQuery, 'matches.manage', 'match_sheets.manage'),
            ],
        ], fn (array $action) => filled($action['url'])));

        return view('admin.dashboard', compact(
            'actions',
            'alerts',
            'availableTournaments',
            'canSeeAllTournaments',
            'liveMatches',
            'scheduleHeading',
            'stats',
            'todaySchedule',
            'tournament',
            'upcomingJornadas',
        ));
    }

    private function resolveTournament(Request $request, User $user): ?Tournament
    {
        $requested = $request->string('tournament_id')->toString();
        $withCounts = fn ($query) => $query->withCount(['categories', 'teams', 'matches', 'delegations']);

        if ($user->canAccessAllTournaments() && ($requested === '' || $requested === 'all')) {
            return null;
        }

        if ($requested !== '' && $requested !== 'all' && $user->canAccessTournament((int) $requested)) {
            return Tournament::query()->tap($withCounts)->find((int) $requested);
        }

        $defaultId = $user->tournament_id ?: ($user->assignedTournamentIds()[0] ?? null);

        if ($defaultId && $user->canAccessTournament((int) $defaultId) && ! $user->canAccessAllTournaments()) {
            return Tournament::query()->tap($withCounts)->find((int) $defaultId);
        }

        return null;
    }

    private function actionUrl(?User $user, string $route, array $params = [], string ...$permissions): ?string
    {
        if (! $user?->hasAnyPermission(...$permissions)) {
            return null;
        }

        return route($route, $params);
    }

    /**
     * @return array{0: CarbonInterface, 1: string}
     */
    private function scheduleWindow(?Tournament $tournament, User $user): array
    {
        $today = now()->startOfDay();
        $matches = FixtureMatch::query()
            ->when($tournament, fn (Builder $query) => $query->where('tournament_id', $tournament->id))
            ->when(
                ! $tournament && ! $user->canAccessAllTournaments(),
                fn (Builder $query) => $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0])
            );

        if ((clone $matches)->whereDate('scheduled_at', $today)->exists()) {
            return [$today, 'Partidos del día'];
        }

        $nextAt = (clone $matches)
            ->where('scheduled_at', '>=', $today)
            ->orderBy('scheduled_at')
            ->value('scheduled_at');

        if ($nextAt) {
            $date = Carbon::parse($nextAt)->startOfDay();

            return [$date, 'Próxima jornada · '.$date->format('d/m')];
        }

        $lastAt = (clone $matches)->orderByDesc('scheduled_at')->value('scheduled_at');

        if ($lastAt) {
            $date = Carbon::parse($lastAt)->startOfDay();

            return [$date, 'Última jornada · '.$date->format('d/m')];
        }

        return [$today, 'Partidos del día'];
    }
}
