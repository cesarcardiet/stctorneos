<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\FixtureMatch;
use App\Models\Player;
use App\Models\RoundSelection;
use App\Models\Sanction;
use App\Models\Team;
use App\Services\CompetitionBoard;
use App\Services\StcRating;
use App\Support\FairPlayRules;
use App\Support\TextSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function __construct(
        private readonly CompetitionBoard $board,
        private readonly StcRating $rating,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = FixtureMatch::query()
            ->accessibleTo($user)
            ->with(['homeTeam', 'awayTeam', 'category', 'field', 'sheet'])
            ->whereIn('status', ['live', 'finished', 'validated']);

        $this->applyFilters($request, $query);

        $matches = $query->orderByDesc('scheduled_at')->get();
        $all = FixtureMatch::query()
            ->accessibleTo($user)
            ->whereIn('status', ['live', 'finished', 'validated'])
            ->get();

        return view('admin.results.index', [
            ...$this->pageData($request, 'index'),
            'matches' => $matches,
            'stats' => [
                [$all->whereIn('status', ['finished', 'validated'])->count(), 'Oficiales', 'green'],
                [$all->where('status', 'live')->count(), 'En juego', 'orange'],
                [$all->whereIn('status', ['finished', 'validated'])->where('published', false)->count(), 'Sin publicar', 'yellow'],
                [$this->board->goalsIn($all->whereIn('status', ['finished', 'validated'])), 'Goles', 'cyan'],
            ],
            'subheading' => $user->canAccessAllTournaments()
                ? 'Resultados oficiales, tablas, cruces, Fair Play y rankings por categoría.'
                : 'Resultados de tus torneos asignados.',
        ]);
    }

    public function standings(Request $request): View
    {
        $category = $this->selectedCategory($request);
        $search = $request->string('search')->toString();
        $groups = $category ? $this->board->standingsByGroup($category) : collect();

        if ($search !== '') {
            $groups = $groups
                ->map(fn (Collection $rows) => $rows->filter(fn (array $row) => TextSearch::matches($search, $row['team']->name))->values())
                ->filter(fn (Collection $rows) => $rows->isNotEmpty());
        }

        return view('admin.results.standings', [
            ...$this->pageData($request, 'standings', $category),
            'groups' => $groups,
        ]);
    }

    public function brackets(Request $request): View
    {
        $category = $this->selectedCategory($request);
        $search = $request->string('search')->toString();
        $brackets = $category ? $this->board->brackets($category) : collect();

        if ($search !== '') {
            $brackets = $brackets
                ->map(fn (Collection $matches) => $matches->filter(fn (FixtureMatch $match) => TextSearch::matches(
                    $search,
                    $match->homeTeam?->name,
                    $match->awayTeam?->name,
                    $match->stage,
                    $match->field?->name
                )))
                ->filter(fn (Collection $matches) => $matches->isNotEmpty());
        }

        return view('admin.results.brackets', [
            ...$this->pageData($request, 'brackets', $category),
            'brackets' => $brackets,
        ]);
    }

    public function fairplay(Request $request): View
    {
        $category = $this->selectedCategory($request);
        $search = $request->string('search')->toString();
        $rows = $category ? $this->board->fairPlay($category) : collect();

        if ($search !== '') {
            $rows = $rows->filter(fn (array $row) => TextSearch::matches($search, $row['team']->name))->values();
        }

        return view('admin.results.fairplay', [
            ...$this->pageData($request, 'fairplay', $category),
            'rows' => $rows,
            'rules' => FairPlayRules::scaleSummary(),
            'tiebreakers' => FairPlayRules::tiebreakerLabels(),
        ]);
    }

    public function rankings(Request $request): View
    {
        $category = $this->selectedCategory($request);
        $search = $request->string('search')->toString();
        $rankings = $category ? $this->board->playerRankings($category) : [];
        $vallas = $category ? $this->board->leastGoalsAgainst($category) : collect();
        $teamStats = $category ? $this->board->teamStatistics($category) : collect();

        if ($search !== '') {
            $rankings = collect($rankings)
                ->map(fn ($players) => collect($players)->filter(fn ($row) => TextSearch::matches($search, $row[0], $row[1]))->values())
                ->all();
            $vallas = $vallas->filter(fn (array $row) => TextSearch::matches($search, $row['team']->name))->values();
            $teamStats = $teamStats->filter(fn (array $row) => TextSearch::matches($search, $row['team']->name))->values();
        }

        return view('admin.results.rankings', [
            ...$this->pageData($request, 'rankings', $category),
            'rankings' => $rankings,
            'vallas' => $vallas,
            'teamStats' => $teamStats,
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $query = FixtureMatch::query()
            ->accessibleTo($request->user())
            ->whereIn('status', ['finished', 'validated']);
        $this->applyFilters($request, $query);

        $count = $query->update([
            'published' => true,
            'published_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Resultados',
            'action' => 'publish',
            'description' => "Resultados oficiales publicados en app: {$count} partidos.",
            'auditable_type' => FixtureMatch::class,
            'metadata' => ['count' => $count],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', $count.' resultados oficiales publicados para la app.');
    }

    public function publishMatch(Request $request, FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);
        abort_unless(in_array($match->status, ['finished', 'validated'], true), 422, 'El partido todavía no está cerrado.');

        $match->update([
            'published' => true,
            'published_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Resultados',
            'action' => 'publish',
            'description' => 'Resultado publicado: '.$match->title().' '.$match->scoreLine(),
            'auditable_type' => FixtureMatch::class,
            'auditable_id' => $match->id,
            'metadata' => ['score' => $match->scoreLine()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Resultado publicado: '.$match->title().' '.$match->scoreLine());
    }

    public function observeMatch(Request $request, FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);

        $match->update([
            'published' => false,
            'published_at' => null,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Resultados',
            'action' => 'observe',
            'description' => 'Resultado observado: '.$match->title().' '.$match->scoreLine(),
            'auditable_type' => FixtureMatch::class,
            'auditable_id' => $match->id,
            'metadata' => ['score' => $match->scoreLine()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Resultado observado. Quedó interno hasta republicarlo.');
    }

    public function sanctions(Request $request): View
    {
        $user = $request->user();
        $sanctions = Sanction::query()
            ->accessibleTo($user)
            ->with(['tournament', 'category', 'team', 'player', 'match', 'author'])
            ->latest()
            ->get();

        return view('admin.results.sanctions', [
            ...$this->pageData($request, 'sanctions'),
            'sanctions' => $sanctions,
            'teams' => Team::query()->accessibleTo($user)->orderBy('name')->get(),
            'types' => Sanction::typeLabels(),
        ]);
    }

    public function storeSanction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'player_id' => ['nullable', 'exists:players,id'],
            'match_id' => ['nullable', 'exists:matches,id'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(Sanction::typeLabels()))],
            'title' => ['required', 'string', 'max:180'],
            'resolution' => ['nullable', 'string', 'max:2000'],
            'points_delta' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'status' => ['required', 'string', 'in:active,resolved,revoked'],
        ]);

        abort_unless($request->user()?->canAccessTournament((int) $data['tournament_id']), 403);
        $data['created_by'] = auth()->id();
        $data['applied_at'] = now();
        $data['points_delta'] = (int) ($data['points_delta'] ?? 0);

        $sanction = Sanction::create($data);
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Resultados',
            'action' => 'sanction',
            'description' => 'Sanción cargada: '.$sanction->title.' ('.$sanction->pointsLabel().')',
            'auditable_type' => Sanction::class,
            'auditable_id' => $sanction->id,
            'metadata' => ['points_delta' => $sanction->points_delta],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Sanción registrada. El impacto en tabla se aplica si está vigente.');
    }

    public function updateSanction(Request $request, Sanction $sanction): RedirectResponse
    {
        abort_unless($request->user()?->canAccessTournament((int) $sanction->tournament_id), 403);
        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,resolved,revoked'],
            'resolution' => ['nullable', 'string', 'max:2000'],
        ]);
        $sanction->update($data);
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Resultados',
            'action' => 'sanction_update',
            'description' => 'Sanción actualizada: '.$sanction->title.' → '.$sanction->statusLabel(),
            'auditable_type' => Sanction::class,
            'auditable_id' => $sanction->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Sanción actualizada.');
    }

    public function rating(Request $request): View
    {
        $category = $this->selectedCategory($request);
        $round = $request->string('round')->toString();
        $rows = $category ? $this->rating->forCategory($category, $round !== '' ? $round : null) : collect();
        $rounds = $category
            ? FixtureMatch::query()->where('category_id', $category->id)->whereNotNull('round')->distinct()->orderBy('round')->pluck('round')
            : collect();

        return view('admin.results.rating', [
            ...$this->pageData($request, 'rating', $category),
            'rows' => $rows,
            'round' => $round,
            'rounds' => $rounds,
        ]);
    }

    public function teamOfRound(Request $request): View
    {
        $category = $this->selectedCategory($request);
        $round = $request->string('round')->toString();
        $rounds = $category
            ? FixtureMatch::query()->where('category_id', $category->id)->whereNotNull('round')->distinct()->orderBy('round')->pluck('round')
            : collect();
        if ($round === '' && $rounds->isNotEmpty()) {
            $round = (string) $rounds->first();
        }

        $proposal = ($category && $round !== '') ? $this->rating->teamOfRound($category, $round) : collect();
        $approved = ($category && $round !== '')
            ? RoundSelection::query()
                ->with(['player', 'team'])
                ->where('category_id', $category->id)
                ->where('round', $round)
                ->where('selected', true)
                ->orderBy('sort_order')
                ->get()
            : collect();
        $candidates = $category
            ? Player::query()
                ->with('team')
                ->whereHas('team', fn ($query) => $query->where('category_id', $category->id))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
            : collect();

        return view('admin.results.team-of-round', [
            ...$this->pageData($request, 'team', $category),
            'round' => $round,
            'rounds' => $rounds,
            'proposal' => $proposal,
            'approved' => $approved,
            'candidates' => $candidates,
        ]);
    }

    public function approveTeamOfRound(Request $request): RedirectResponse
    {
        $category = $this->selectedCategory($request);
        abort_unless($category, 422, 'Elegí una categoría.');
        $round = $request->string('round')->toString();
        abort_unless($round !== '', 422, 'Elegí una fecha del fixture.');

        $proposal = $this->rating->teamOfRound($category, $round);
        RoundSelection::query()->where('category_id', $category->id)->where('round', $round)->delete();

        foreach ($proposal as $index => $row) {
            RoundSelection::create([
                'category_id' => $category->id,
                'round' => $round,
                'player_id' => $row['player']->id,
                'team_id' => $row['player']->team_id,
                'position' => $row['position'],
                'rating' => $row['points'],
                'sort_order' => $index + 1,
                'selected' => true,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Resultados',
            'action' => 'team_of_round',
            'description' => 'Equipo de la Fecha aprobado: '.$category->name.' · '.$round,
            'auditable_type' => Category::class,
            'auditable_id' => $category->id,
            'metadata' => ['round' => $round, 'count' => $proposal->count()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Equipo de la Fecha aprobado para '.$round.'.');
    }

    public function replaceTeamOfRound(Request $request, RoundSelection $selection): RedirectResponse
    {
        $category = $this->selectedCategory($request);
        abort_unless($category, 422, 'Elegí una categoría.');
        abort_unless((int) $selection->category_id === (int) $category->id, 404);

        $data = $request->validate([
            'player_id' => ['required', 'exists:players,id'],
            'round' => ['required', 'string', 'max:80'],
        ]);

        abort_unless($selection->round === $data['round'], 422, 'La fecha no coincide.');

        $player = Player::query()->with('team')->findOrFail($data['player_id']);
        abort_unless((int) $player->team?->category_id === (int) $category->id, 422, 'El jugador no es de esta categoría.');
        abort_unless(
            $request->user()?->canAccessTournament((int) $category->tournament_id),
            403
        );

        $duplicate = RoundSelection::query()
            ->where('category_id', $category->id)
            ->where('round', $data['round'])
            ->where('player_id', $player->id)
            ->whereKeyNot($selection->id)
            ->exists();
        abort_if($duplicate, 422, 'Ese jugador ya está en el Equipo de la Fecha.');

        $previous = $selection->player?->fullName();
        $selection->update([
            'player_id' => $player->id,
            'team_id' => $player->team_id,
            'position' => $player->position,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Resultados',
            'action' => 'team_of_round_replace',
            'description' => 'Sustitución en Equipo de la Fecha: '.($previous ?: 'jugador').' → '.$player->fullName(),
            'auditable_type' => RoundSelection::class,
            'auditable_id' => $selection->id,
            'metadata' => ['round' => $data['round'], 'player_id' => $player->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Jugador sustituido en el Equipo de la Fecha.');
    }

    public function updateFairPlay(Request $request): RedirectResponse
    {
        $category = $this->selectedCategory($request);
        abort_unless($category, 422, 'Elegí una categoría.');
        abort_unless($request->user()?->canAccessTournament((int) $category->tournament_id), 403);

        $data = $request->validate([
            'fair_play_yellow' => ['required', 'integer', 'min:0', 'max:10'],
            'fair_play_red' => ['required', 'integer', 'min:0', 'max:10'],
            'fair_play_incident' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $category->update($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Resultados',
            'action' => 'fair_play_config',
            'description' => 'Puntaje Fair Play actualizado: '.$category->name,
            'auditable_type' => Category::class,
            'auditable_id' => $category->id,
            'metadata' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Puntaje Fair Play actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Request $request, string $tab, ?Category $category = null): array
    {
        $user = $request->user();
        $filters = $this->filters($request);
        $tournaments = $user->accessibleTournaments();
        $categories = Category::query()
            ->accessibleTo($user)
            ->with('tournament')
            ->when($filters['tournament_id'] !== '', function ($query) use ($request, $user, $filters) {
                abort_unless($user?->canAccessTournament((int) $filters['tournament_id']), 403, 'Este torneo no está dentro de tu alcance.');
                $query->where('tournament_id', $filters['tournament_id']);
            })
            ->orderBy('name')
            ->get();

        if (! $category) {
            $category = $this->selectedCategory($request, $categories);
        }

        return [
            'filters' => $filters,
            'tab' => $tab,
            'category' => $category,
            'categories' => $categories,
            'tournaments' => $tournaments,
            'accessibleTournaments' => $tournaments,
            'subheading' => $user->canAccessAllTournaments()
                ? 'Resultados oficiales, tablas, cruces, Fair Play y rankings por categoría.'
                : 'Resultados de tus torneos asignados.',
        ];
    }

    /**
     * @return array{search: string, category_id: string, tournament_id: string, status: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString(),
            'category_id' => $request->string('category_id')->toString(),
            'tournament_id' => $request->string('tournament_id')->toString(),
            'status' => $request->string('status', 'all')->toString(),
        ];
    }

    private function selectedCategory(Request $request, $categories = null): ?Category
    {
        $user = $request->user();

        if ($request->filled('category_id')) {
            $category = Category::query()->accessibleTo($user)->with('tournament')->find($request->integer('category_id'));
            abort_unless($category, 403, 'Esta categoría no está dentro de tu alcance.');

            return $category;
        }

        $pool = $categories ?? Category::query()->accessibleTo($user)->with('tournament')->orderBy('name')->get();
        $preferred = $pool->firstWhere('name', 'Sub 12 Masculino');

        return $preferred ?: $pool->first();
    }

    private function applyFilters(Request $request, $query): void
    {
        $user = $request->user();
        $filters = $this->filters($request);

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($query) use ($search) {
                $query
                    ->where('stage', 'like', "%{$search}%")
                    ->orWhere('round', 'like', "%{$search}%")
                    ->orWhere('referee_name', 'like', "%{$search}%")
                    ->orWhereHas('homeTeam', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('awayTeam', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            });
        }

        if ($filters['category_id'] !== '') {
            $query->where('category_id', $filters['category_id']);
        }

        if ($filters['tournament_id'] !== '') {
            abort_unless($user?->canAccessTournament((int) $filters['tournament_id']), 403, 'Este torneo no está dentro de tu alcance.');
            $query->where('tournament_id', $filters['tournament_id']);
        }

        if ($filters['status'] !== '' && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
    }

    private function assertMatchAccess(FixtureMatch $match): void
    {
        abort_unless(
            auth()->user()?->canAccessTournament((int) $match->tournament_id),
            403,
            'Este partido no está dentro de tu alcance.'
        );
    }
}
