<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Services\CompetitionBoard;
use App\Services\RegistrationControl;
use App\Support\AdminContext;
use App\Support\CategoryWorkspace;
use App\Support\FairPlayRules;
use App\Support\TextSearch;
use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $accessibleTournaments = $user->accessibleTournaments();

        $filters = AdminContext::applyTournamentFilter($request, [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status', 'all')->toString(),
            'modality' => $request->string('modality', 'all')->toString(),
            'tournament_id' => $request->string('tournament_id')->toString(),
        ]);

        $allCategories = Category::query()
            ->accessibleTo($user)
            ->with('tournament')
            ->withCount('teams')
            ->latest()
            ->get();

        $categories = Category::query()
            ->accessibleTo($user)
            ->with('tournament')
            ->withCount('teams')
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('birth_year', 'like', "%{$search}%")
                        ->orWhere('branch', 'like', "%{$search}%")
                        ->orWhere('modality', 'like', "%{$search}%")
                        ->orWhere('custom_modality', 'like', "%{$search}%")
                        ->orWhere('competition_format', 'like', "%{$search}%")
                        ->orWhereHas('tournament', fn ($tournament) => $tournament->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] !== '' && $filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['tournament_id'] !== '', function ($query) use ($filters, $user) {
                $tournamentId = (int) $filters['tournament_id'];
                abort_unless($user->canAccessTournament($tournamentId), 403, 'Este torneo no está dentro de tu alcance.');
                $query->where('tournament_id', $tournamentId);
            })
            ->when($filters['modality'] !== '' && $filters['modality'] !== 'all', fn ($query) => $query->where('modality', $filters['modality']))
            ->latest()
            ->get();

        $stats = [
            ['Categorías activas', $allCategories->where('status', 'active')->count(), 'blue'],
            ['Equipos vinculados', $allCategories->sum('teams_count'), 'cyan'],
            ['Modalidades', $allCategories->pluck('modality')->unique()->count(), 'green'],
            ['Formatos', $allCategories->pluck('competition_format')->unique()->count(), 'yellow'],
        ];

        $modalities = collect(Category::modalities())
            ->merge($allCategories->pluck('modality'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $selectedTournament = $accessibleTournaments->firstWhere('id', (int) $filters['tournament_id']);
        $subheading = $selectedTournament
            ? 'Listado de categorías del torneo activo — '.$selectedTournament->name
            : ($user->canAccessAllTournaments()
                ? 'Listado de categorías del torneo activo'
                : 'Categorías de tus torneos asignados');

        return view('admin.categories.index', compact(
            'categories',
            'stats',
            'filters',
            'modalities',
            'accessibleTournaments',
            'subheading',
        ));
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category([
                'branch' => 'Masculina',
                'modality' => 'Fútbol 11',
                'format' => 'Fútbol 11',
                'image_path' => null,
                'competition_format' => '',
                'status' => 'active',
                'points_win' => 3,
                'points_draw' => 1,
                'points_loss' => 0,
                'groups_count' => 0,
                'teams_per_group' => 0,
                'qualifiers_count' => 8,
            ]),
            'method' => 'POST',
            'title' => 'Nueva categoría',
            'tournaments' => auth()->user()->accessibleTournaments(),
            'url' => route('admin.categories.store'),
            'bannerLogos' => Category::bannerLogoChoicesForTournament(0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = Category::create($this->validatedData($request));
        $category->applyBannerFromRequest($request);

        $this->audit('create', $category, 'Categoría creada desde Admin Web.');

        return redirect()
            ->route('admin.categories.show', $category)
            ->with('status', 'Categoría creada correctamente.');
    }

    public function show(Category $category): View
    {
        $this->assertCategoryAccess($category);
        AdminContext::rememberCategory($category);

        $category
            ->load(['tournament', 'teams' => fn ($query) => $query->with('delegation')->withCount('players')])
            ->loadCount(['teams', 'matches']);

        return view('admin.categories.show', [
            'category' => $category,
            'tournament' => $category->tournament,
        ]);
    }

    public function competition(Request $request, Category $category, CompetitionBoard $board): View
    {
        AdminContext::rememberCategory($category);

        return view('admin.categories.competition', $this->classificationBoard($request, $category, $board));
    }

    public function rankings(Request $request, Category $category, CompetitionBoard $board): View
    {
        AdminContext::rememberCategory($category);

        if (! $request->filled('tab')) {
            $request->merge(['tab' => 'rankings']);
        }

        return view('admin.categories.competition', $this->classificationBoard($request, $category, $board));
    }

    public function edit(Category $category): View
    {
        $this->assertCategoryAccess($category);

        return view('admin.categories.form', [
            'category' => $category,
            'method' => 'PUT',
            'title' => 'Editar categoría',
            'tournaments' => auth()->user()->accessibleTournaments(),
            'url' => route('admin.categories.update', $category),
            'bannerLogos' => $category->bannerLogoChoices(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->assertCategoryAccess($category);
        $category->update($this->validatedData($request));
        $category->applyBannerFromRequest($request);

        $this->audit('update', $category, 'Categoría actualizada desde Admin Web.');

        return redirect()
            ->route('admin.categories.show', $category)
            ->with('status', 'Categoría actualizada correctamente.');
    }

    public function updateStatus(Request $request, Category $category): RedirectResponse
    {
        $this->assertCategoryAccess($category);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,draft'],
        ]);

        $category->update($data);

        $this->audit('status_update', $category, 'Estado de categoría actualizado desde el listado.');

        return back()->with('status', 'Estado de la categoría actualizado.');
    }

    public function toggleRegistrations(Request $request, Category $category): RedirectResponse
    {
        $this->assertCategoryAccess($category);

        $data = $request->validate([
            'open' => ['required', 'boolean'],
            'registration_info' => ['nullable', 'string', 'max:4000'],
        ]);

        $open = $request->boolean('open');
        RegistrationControl::setCategoryOpen($category, $open, $data['registration_info'] ?? null);

        $this->audit(
            'update',
            $category,
            ($open ? 'Inscripciones abiertas' : 'Inscripciones cerradas').' en '.$category->name.'.'
        );

        return back()->with(
            'status',
            $open
                ? 'Inscripciones abiertas. Los delegados pueden modificar planteles.'
                : 'Inscripciones cerradas. Los delegados ya no pueden modificar planteles.'
        );
    }

    public function duplicate(Category $category): RedirectResponse
    {
        $this->assertCategoryAccess($category);

        $copy = $category->replicate();
        $copy->name = 'Copia de '.$category->name;
        $copy->status = 'draft';
        $copy->save();

        $this->audit('duplicate', $copy, 'Categoría duplicada desde '.$category->name.'.');

        return redirect()
            ->route('admin.categories.edit', $copy)
            ->with('status', 'Categoría duplicada en estado Borrador. No se copiaron equipos ni partidos.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->assertCategoryAccess($category);
        $this->audit('delete', $category, 'Categoría eliminada desde Admin Web.');

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Categoría eliminada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'name' => ['required', 'string', 'max:180'],
            'birth_year' => ['required', 'string', 'max:9', 'regex:/^\d{4}(\/\d{4})?$/'],
            'branch' => ['required', 'string', 'max:80'],
            'modality' => ['required', 'string', 'max:80'],
            'custom_modality' => [
                Rule::requiredIf(fn () => $request->input('modality') === 'Personalizada'),
                'nullable',
                'string',
                'max:120',
            ],
            'image_path' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'file', 'max:8192'],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
            'team_limit' => ['required', 'integer', 'min:2', 'max:128'],
            'min_players' => ['required', 'integer', 'min:1', 'max:40'],
            'max_players' => ['required', 'integer', 'gte:min_players', 'max:60'],
            'players_on_field' => ['required', 'integer', 'min:1', 'max:11'],
            'substitutes' => ['required', 'integer', 'min:0', 'max:30'],
            'periods' => ['required', 'integer', 'min:1', 'max:4'],
            'period_duration' => ['required', 'integer', 'min:1', 'max:90'],
            'competition_format' => ['required', 'string', 'max:120'],
            'custom_competition_format' => [
                Rule::requiredIf(fn () => $request->input('competition_format') === 'Formato personalizado'),
                'nullable',
                'string',
                'max:120',
            ],
            'groups_count' => [
                Rule::requiredIf(fn () => Category::formatNeedsGroups((string) $request->input('competition_format'))),
                'nullable',
                'integer',
                'min:0',
                'max:'.Category::MAX_GROUPS,
            ],
            'teams_per_group' => ['required', 'integer', 'min:0', 'max:32'],
            'qualifiers_count' => ['required', 'integer', 'min:0', 'max:128'],
            'phases' => ['nullable', 'string', 'max:255'],
            'brackets' => ['nullable', 'string', 'max:1200'],
            'classification_criteria' => ['nullable', 'string', 'max:1200'],
            'points_win' => ['required', 'integer', 'min:0', 'max:10'],
            'points_draw' => ['required', 'integer', 'min:0', 'max:10'],
            'points_loss' => ['required', 'integer', 'min:0', 'max:10'],
            'tiebreakers' => ['nullable', 'string', 'max:500'],
            'rules' => ['nullable', 'string', 'max:1200'],
            'discipline_rules' => ['nullable', 'string', 'max:1200'],
            'fair_play_yellow' => ['nullable', 'integer', 'min:0', 'max:10'],
            'fair_play_red' => ['nullable', 'integer', 'min:0', 'max:10'],
            'fair_play_incident' => ['nullable', 'integer', 'min:0', 'max:10'],
            'status' => ['required', 'string', 'in:active,inactive,draft'],
        ]);

        abort_unless(
            $request->user()->canAccessTournament((int) $data['tournament_id']),
            403,
            'Este torneo no está dentro de tu alcance.'
        );

        $data['format'] = $data['modality'] === 'Personalizada'
            ? ($data['custom_modality'] ?: $data['modality'])
            : $data['modality'];
        unset($data['image_file'], $data['shield_file'], $data['shield_data'], $data['image_path']);

        if ($data['modality'] !== 'Personalizada') {
            $data['custom_modality'] = null;
        }

        $data['competition_format'] = Category::resolveCompetitionFormat(
            $data['competition_format'],
            $data['custom_competition_format'] ?? null,
        );
        unset($data['custom_competition_format']);

        if ($data['competition_format'] === '') {
            throw ValidationException::withMessages([
                'competition_format' => 'Elegí o escribí el formato de competencia.',
            ]);
        }

        if (Category::formatNeedsGroups((string) $request->input('competition_format'))) {
            if ((int) ($data['groups_count'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    'groups_count' => 'Indicá cuántos grupos tiene este formato.',
                ]);
            }
        } else {
            $data['groups_count'] = 0;
            $data['teams_per_group'] = 0;
        }

        $data['tiebreakers'] = collect(explode("\n", (string) ($data['tiebreakers'] ?? '')))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();

        $data['fair_play_yellow'] = $data['fair_play_yellow'] ?? 1;
        $data['fair_play_red'] = $data['fair_play_red'] ?? 3;
        $data['fair_play_incident'] = $data['fair_play_incident'] ?? 2;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function classificationBoard(Request $request, Category $category, CompetitionBoard $board): array
    {
        $this->assertCategoryAccess($category);
        $user = $request->user();
        $category->load(['tournament', 'teams.delegation'])->loadCount('teams');

        $tab = $request->string('tab', 'standings')->toString();
        if (! in_array($tab, ['standings', 'brackets', 'fairplay', 'rankings'], true)) {
            $tab = 'standings';
        }

        $matches = FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'category', 'sheet', 'penaltyKicks'])
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

        $selectedRound = $request->string('round', 'all')->toString();
        if ($selectedRound === '') {
            $selectedRound = 'all';
        }

        $search = $request->string('search')->toString();
        $visibleMatches = $matches
            ->when($selectedPhase !== 'all', fn ($collection) => $collection->filter(
                fn ($match) => CategoryWorkspace::matchPhase($match, $phases) === $selectedPhase
            ))
            ->when($selectedRound !== 'all', fn ($collection) => $collection->filter(
                fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage) === $selectedRound
            ))
            ->when($search !== '', fn ($collection) => $collection->filter(fn ($match) => TextSearch::matches(
                $search,
                $match->homeTeam?->name,
                $match->awayTeam?->name,
                $match->stage,
                $match->field?->name
            )))
            ->values();

        $groups = $board->standingsByGroup($category);
        if ($search !== '') {
            $groups = $groups
                ->map(fn ($rows) => $rows->filter(fn (array $row) => TextSearch::matches($search, $row['team']->name))->values())
                ->filter(fn ($rows) => $rows->isNotEmpty());
        }

        $rankings = $board->playerRankings($category);
        if ($search !== '') {
            $rankings = collect($rankings)
                ->map(fn ($players) => collect($players)->filter(fn ($row) => TextSearch::matches($search, $row[0], $row[1]))->values())
                ->all();
        }

        $canEdit = (bool) $user?->hasAnyPermission(
            'tournaments.manage',
            'delegations.manage',
            'matches.manage',
            'players.approve',
        );

        return [
            'category' => $category,
            'tournament' => $category->tournament,
            'categories' => $this->siblingCategories($request, $category),
            'tab' => $tab,
            'groups' => $groups,
            'matches' => $matches,
            'visibleMatches' => $visibleMatches,
            'phases' => $phases,
            'rounds' => $rounds,
            'selectedPhase' => $selectedPhase,
            'selectedRound' => $selectedRound,
            'dates' => $rounds,
            'selectedDate' => $selectedRound,
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
            'highlightFirst' => (int) $workspace['highlight_first'],
            'highlightLast' => (int) $workspace['highlight_last'],
            'teams' => $category->teams,
            'fields' => Field::query()
                ->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))
                ->with('venue')
                ->orderBy('name')
                ->get(),
            'canEdit' => $canEdit,
            'canScheduleMatches' => (bool) $user?->hasPermission('tournaments.manage'),
            'canDeleteMatches' => (bool) $user?->isSuperAdmin(),
            'canViewPlanillas' => WorkspaceAccess::for($user)->canViewPlanillas(),
            'defaultStart' => optional($category->tournament?->starts_at)->format('Y-m-d\T09:00') ?: now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'),
            'brackets' => $board->brackets($category),
            'fairPlayRows' => $board->fairPlay($category),
            'fairPlayRules' => FairPlayRules::scaleSummary(),
            'fairPlayTiebreakers' => FairPlayRules::tiebreakerLabels(),
            'rankings' => $rankings,
            'leastGoals' => $board->leastGoalsAgainst($category),
        ];
    }

    private function siblingCategories(Request $request, Category $category)
    {
        return Category::query()
            ->accessibleTo($request->user())
            ->where('tournament_id', $category->tournament_id)
            ->orderBy('name')
            ->get();
    }

    private function assertCategoryAccess(Category $category): void
    {
        abort_unless(
            auth()->user()?->canAccessTournament((int) $category->tournament_id),
            403,
            'Esta categoría no está dentro de tu alcance.'
        );
    }

    private function audit(string $action, Category $category, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Categorías',
            'action' => $action,
            'description' => $description,
            'auditable_type' => Category::class,
            'auditable_id' => $category->id,
            'metadata' => ['name' => $category->name, 'tournament_id' => $category->tournament_id],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
