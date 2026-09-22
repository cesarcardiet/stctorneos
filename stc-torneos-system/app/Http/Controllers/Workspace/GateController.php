<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\ResolvesWorkspace;
use App\Models\Category;
use App\Models\Delegation;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\RegistrationControl;
use App\Services\TournamentPurger;
use App\Services\WorkspaceCategorySetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GateController extends Controller
{
    use ResolvesWorkspace;

    public function home(Request $request): View|RedirectResponse
    {
        $tournaments = $request->user()->accessibleTournaments();
        $tournaments->loadCount(['categories', 'teams']);

        return view('workspace.gate.tournaments', [
            'tournaments' => $tournaments,
            'title' => 'Elegí un torneo',
            'active' => 'Torneos',
            'canCreateTournaments' => (bool) $request->user()?->isSuperAdmin(),
            'canEditTournaments' => (bool) $request->user()?->hasPermission('tournaments.manage'),
            'canDeleteTournaments' => (bool) $request->user()?->isSuperAdmin(),
        ]);
    }

    public function account(Request $request): View
    {
        $user = $request->user();
        $tournament = $user->accessibleTournaments()->first();

        return view('workspace.account', [
            'user' => $user,
            'tournament' => $tournament,
            'category' => null,
            'title' => 'Mi cuenta',
            'heading' => 'Mi cuenta',
            'subheading' => $user->email,
            'active' => 'Cuenta',
            'access' => \App\Support\WorkspaceAccess::for($user),
        ]);
    }

    public function myClub(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->restrictsToAssignedClub(), 403);

        $portfolio = $user->delegateClubPortfolio();
        if ($portfolio->isEmpty()) {
            return redirect()
                ->route('workspace.home')
                ->with('status', 'Todavía no tenés un club asignado. Pedile al organizador que te vincule a una delegación.');
        }

        $allTeams = $user->delegateTeams()
            ->sortBy([
                fn (Team $team) => $team->category && $team->category->acceptsRosterEdits($team) ? 0 : 1,
                fn (Team $team) => $team->category?->tournament?->name ?? '',
                fn (Team $team) => $team->category?->name ?? '',
                fn (Team $team) => $team->name,
            ])
            ->values();

        $highlightTournamentId = $request->integer('tournament') ?: null;
        $navTournament = $highlightTournamentId
            ? Tournament::query()->find($highlightTournamentId)
            : $portfolio->first()['tournament'] ?? null;

        return view('workspace.my-club', [
            'portfolio' => $portfolio,
            'allTeams' => $allTeams,
            'highlightTournamentId' => $highlightTournamentId,
            'title' => 'Mi club',
            'heading' => 'Mi club',
            'subheading' => $portfolio->count() === 1
                ? ($portfolio->first()['club']->name ?? 'Tus equipos')
                : 'Todos tus equipos en '.$portfolio->count().' torneos',
            'tournament' => $navTournament,
            'category' => null,
            'active' => 'Delegaciones',
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'La clave actual no coincide.',
            'password.min' => 'La nueva clave tiene que tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación no coincide.',
        ]);

        $request->user()->update([
            'password' => $data['password'],
        ]);
        $request->session()->regenerate();

        return back()->with('status', 'Tu clave quedó actualizada. La próxima vez entrá con la nueva.');
    }

    public function directory(Request $request): View
    {
        $query = trim((string) $request->string('q'));

        $categories = Category::query()
            ->accessibleTo($request->user())
            ->with('tournament')
            ->withCount(['teams', 'players', 'matches'])
            ->orderBy('name')
            ->orderBy('birth_year')
            ->get();

        if ($query !== '') {
            $needle = mb_strtolower($query);
            $categories = $categories->filter(function (Category $category) use ($needle) {
                $haystack = mb_strtolower(trim(implode(' ', [
                    $category->name,
                    $category->birth_year,
                    $category->branch,
                    $category->tournament?->name,
                ])));

                return str_contains($haystack, $needle);
            })->values();
        }

        $groups = $categories
            ->groupBy(function (Category $category) {
                return mb_strtolower(trim($category->name)).'|'.mb_strtolower(trim((string) $category->birth_year)).'|'.mb_strtolower(trim((string) $category->branch));
            })
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'name' => $first->name,
                    'birth_year' => $first->birth_year,
                    'branch' => $first->branch,
                    'modality' => $first->modalityLabel(),
                    'tournaments_count' => $items->pluck('tournament_id')->unique()->count(),
                    'teams_count' => $items->sum('teams_count'),
                    'categories' => $items
                        ->sortBy(fn (Category $category) => $category->tournament?->name ?? '', SORT_NATURAL | SORT_FLAG_CASE)
                        ->values(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $letters = $groups
            ->map(fn (array $group) => Category::letterFromName((string) $group['name']))
            ->unique()
            ->values();
        $letters = $letters
            ->reject(fn (string $letter) => $letter === '#')
            ->sort()
            ->values()
            ->concat($letters->contains('#') ? ['#'] : []);

        return view('workspace.gate.directory', [
            'groups' => $groups,
            'letters' => $letters,
            'query' => $query,
            'title' => 'Categorías',
            'active' => 'Categorías',
        ]);
    }

    public function tournament(Request $request, Tournament $tournament): View
    {
        $this->assertTournament($tournament);

        $categories = Category::query()
            ->accessibleTo($request->user())
            ->where('tournament_id', $tournament->id)
            ->withCount([
                'teams',
                'players',
                'matches',
                'players as pending_players_count' => fn ($query) => $query->whereIn('players.status', ['pending', 'observed', 'submitted', 'awaiting_guardian']),
                'players as enabled_players_count' => fn ($query) => $query->whereIn('players.status', ['enabled', 'approved']),
                'matches as finished_matches_count' => fn ($query) => $query->whereIn('matches.status', ['finished', 'validated']),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categoryIds = $categories->pluck('id')->all();
        $pendingDocs = $categoryIds === []
            ? collect()
            : PlayerDocument::query()
                ->selectRaw('teams.category_id as category_id, COUNT(*) as total')
                ->join('players', 'players.id', '=', 'player_documents.player_id')
                ->join('teams', 'teams.id', '=', 'players.team_id')
                ->whereIn('teams.category_id', $categoryIds)
                ->whereIn('player_documents.status', ['pending', 'observed'])
                ->groupBy('teams.category_id')
                ->pluck('total', 'category_id');

        $categories->each(function (Category $category) use ($pendingDocs) {
            $category->pending_docs_count = (int) ($pendingDocs[$category->id] ?? 0);
        });

        $letters = $categories
            ->map(fn (Category $category) => $category->sortLetter())
            ->unique()
            ->values();
        $letters = $letters
            ->reject(fn (string $letter) => $letter === '#')
            ->sort()
            ->values()
            ->concat($letters->contains('#') ? ['#'] : []);

        return view('workspace.gate.categories', [
            'tournament' => $tournament,
            'categories' => $categories,
            'letters' => $letters,
            'title' => $tournament->name,
            'canEdit' => $request->user()->hasPermission('tournaments.manage'),
            'canDeleteTournaments' => (bool) $request->user()?->isSuperAdmin(),
            'modalities' => Category::modalities(),
            'formats' => Category::competitionFormats(),
        ]);
    }

    public function reorderCategories(Request $request, Tournament $tournament): JsonResponse
    {
        $this->assertTournament($tournament);
        abort_unless($request->user()->hasPermission('tournaments.manage'), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $owned = Category::query()
            ->accessibleTo($request->user())
            ->where('tournament_id', $tournament->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->unique()->values();

        abort_unless(
            $ids->sort()->values()->all() === $owned->all(),
            422,
            'El orden tiene que incluir todas las categorías de este torneo.'
        );

        foreach ($ids as $index => $id) {
            Category::query()->where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }

    public function updateCategory(Request $request, Tournament $tournament, Category $category): RedirectResponse
    {
        $this->assertTournament($tournament);
        abort_unless((int) $category->tournament_id === (int) $tournament->id, 404);
        abort_unless($request->user()->hasPermission('tournaments.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'birth_year' => ['required', 'string', 'max:80'],
            'branch' => ['required', 'string', 'max:80'],
            'modality' => ['required', 'string', 'max:80'],
            'competition_format' => ['nullable', 'string', 'max:120'],
            'groups_count' => ['nullable', 'integer', 'min:0', 'max:8'],
            'status' => ['required', 'string', 'in:active,inactive,draft'],
            'points_win' => ['nullable', 'integer', 'min:0', 'max:10'],
            'points_draw' => ['nullable', 'integer', 'min:0', 'max:10'],
            'points_loss' => ['nullable', 'integer', 'min:0', 'max:10'],
            'description' => ['nullable', 'string', 'max:2000'],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
            'image_file' => ['nullable', 'file', 'max:8192'],
            'image_path' => ['nullable', 'string', 'max:500'],
        ]);

        $category->update([
            'name' => $data['name'],
            'birth_year' => $data['birth_year'],
            'branch' => $data['branch'],
            'modality' => $data['modality'],
            'format' => $data['modality'],
            'competition_format' => $data['competition_format'] ?? $category->competition_format,
            'groups_count' => $data['groups_count'] ?? $category->groups_count,
            'status' => $data['status'],
            'points_win' => $data['points_win'] ?? $category->points_win,
            'points_draw' => $data['points_draw'] ?? $category->points_draw,
            'points_loss' => $data['points_loss'] ?? $category->points_loss,
        ]);
        $category->mergeWorkspace([
            'description' => $data['description'] ?? '',
        ]);

        $incomingImage = trim((string) $request->input('image_path', ''));
        $hasUpload = $request->filled('shield_data')
            || $request->hasFile('shield_file')
            || $request->hasFile('image_file');
        if ($hasUpload || ($incomingImage !== '' && $incomingImage !== (string) $category->image_path)) {
            $category->applyBannerFromRequest($request);
        }

        $this->audit('update', $category, 'Categoría editada desde el listado: '.$category->name);

        return redirect()
            ->route('workspace.tournaments.show', $tournament)
            ->with('status', $category->name.' actualizada.');
    }

    public function destroyCategory(Request $request, Tournament $tournament, Category $category): RedirectResponse
    {
        $this->assertTournament($tournament);
        abort_unless((int) $category->tournament_id === (int) $tournament->id, 404);
        abort_unless($request->user()->hasPermission('tournaments.manage'), 403);

        $name = $category->name;
        $this->audit('delete', $category, 'Categoría eliminada desde Operación: '.$name);
        $category->delete();

        return redirect()
            ->route('workspace.tournaments.show', $tournament)
            ->with('status', $name.' fue eliminada, con equipos y partidos.');
    }

    public function storeCategory(Request $request, Tournament $tournament, WorkspaceCategorySetup $setup): RedirectResponse
    {
        $this->assertTournament($tournament);
        abort_unless($request->user()->hasPermission('tournaments.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'birth_year' => ['required', 'string', 'max:80'],
            'branch' => ['required', 'string', 'max:80'],
            'modality' => ['required', 'string', 'max:80'],
            'competition_format' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rules' => ['nullable', 'string', 'max:4000'],
            'groups_count' => ['nullable', 'integer', 'min:1', 'max:8'],
            'points_win' => ['nullable', 'integer', 'min:0', 'max:10'],
            'points_draw' => ['nullable', 'integer', 'min:0', 'max:10'],
            'points_loss' => ['nullable', 'integer', 'min:0', 'max:10'],
            'prize_first' => ['nullable', 'string', 'max:180'],
            'prize_second' => ['nullable', 'string', 'max:180'],
            'prize_third' => ['nullable', 'string', 'max:180'],
            'prize_other' => ['nullable', 'string', 'max:180'],
            'teams' => ['nullable', 'array'],
            'teams.*.name' => ['nullable', 'string', 'max:180'],
            'teams.*.group' => ['nullable', 'string', 'max:4'],
            'teams.*.players' => ['nullable', 'string', 'max:4000'],
        ]);

        $category = $setup->create($tournament, $data);

        $teams = $category->teams()->count();
        $players = $category->teams()->withCount('players')->get()->sum('players_count');

        $this->audit(
            'create',
            $category,
            $teams > 0
                ? 'Categoría creada con '.$teams.' equipos y '.$players.' jugadores: '.$category->name
                : 'Categoría creada sin equipos: '.$category->name
        );

        $message = $teams > 0
            ? $category->name.' lista: '.$teams.' equipos y '.$players.' jugadores.'
            : $category->name.' creada. Agregá equipos desde Operación cuando quieras.';

        return redirect()
            ->route('workspace.categories.teams', $category)
            ->with('status', $message);
    }

    public function storeTournament(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Solo el Admin General puede crear torneos.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'edition' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at', 'before_or_equal:2100-12-31'],
        ]);

        $city = trim((string) ($data['city'] ?? 'Santa Teresita'));
        $country = trim((string) ($data['country'] ?? 'Argentina'));
        $startsAt = $data['starts_at'] ?? now()->addMonths(2)->toDateString();
        $endsAt = $data['ends_at'] ?? now()->addMonths(2)->addDays(6)->toDateString();
        $name = trim($data['name']);
        $slug = Str::slug($name);
        $candidate = $slug;
        $counter = 2;

        while (Tournament::where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$counter}";
            $counter++;
        }

        $tournament = Tournament::create([
            'name' => $name,
            'slug' => $candidate,
            'edition' => $data['edition'] ?? ('Edición '.now()->year),
            'city' => $city,
            'country' => $country,
            'location' => "{$city}, {$country}",
            'venue_name' => trim((string) ($data['venue_name'] ?? 'Complejo Deportivo '.$city)),
            'timezone' => 'America/Argentina/Buenos_Aires',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'draft',
            'visibility' => 'private',
        ]);

        $this->audit('create', $tournament, 'Torneo creado desde Operación: '.$tournament->name);

        return redirect()
            ->route('workspace.tournaments.show', $tournament)
            ->with('status', $tournament->name.' creado. Ahora podés cargar categorías.');
    }

    public function toggleRegistrations(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->assertTournament($tournament);
        abort_unless($request->user()->hasPermission('tournaments.manage'), 403);

        $request->validate([
            'open' => ['required', 'boolean'],
        ]);

        $open = $request->boolean('open');
        RegistrationControl::setTournamentOpen($tournament, $open);
        $this->audit(
            'update',
            $tournament,
            ($open ? 'Inscripciones abiertas' : 'Inscripciones cerradas').' en todas las categorías de '.$tournament->name.'.'
        );

        return redirect()
            ->route('workspace.tournaments.show', $tournament)
            ->with(
                'status',
                $open
                    ? 'Inscripciones abiertas en todas las categorías.'
                    : 'Inscripciones cerradas en todas las categorías.'
            );
    }

    public function destroyTournament(Request $request, Tournament $tournament, TournamentPurger $purger): RedirectResponse
    {
        $this->assertTournament($tournament);
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Solo el Admin General puede eliminar torneos.');

        $name = $tournament->name;
        $this->audit('delete', $tournament, 'Torneo eliminado desde Operación: '.$name);
        $purger->delete($tournament);

        return redirect()
            ->route('workspace.home')
            ->with('status', $name.' fue eliminado, con categorías, equipos y partidos.');
    }

    public function lookupKnownClubLogo(Request $request): JsonResponse
    {
        abort_unless($this->canEdit($request->user()), 403);

        $name = trim($request->string('name')->toString());
        $path = Delegation::knownLogoPathForName($name);

        return response()->json([
            'found' => $path !== null,
            'url' => $path
                ? (str_starts_with($path, 'http') ? $path : asset($path))
                : asset('images/stc-logo.png'),
        ]);
    }

    public function clubs(Request $request, Tournament $tournament): View
    {
        $this->assertTournament($tournament);

        Delegation::query()
            ->where('tournament_id', $tournament->id)
            ->get()
            ->each(fn (Delegation $club) => $club->claimOrphanTeams());

        $clubs = Delegation::query()
            ->where('tournament_id', $tournament->id)
            ->with(['delegates.roles'])
            ->withCount(['teams', 'delegates'])
            ->orderBy('name')
            ->get();

        $categories = Category::query()
            ->accessibleTo($request->user())
            ->where('tournament_id', $tournament->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('workspace.clubs', [
            'tournament' => $tournament,
            'category' => null,
            'clubs' => $clubs,
            'categories' => $categories,
            'title' => $tournament->name.' · Delegaciones',
            'active' => 'Delegaciones',
            'canEdit' => $this->canEdit($request->user()),
        ]);
    }

    public function club(Request $request, Tournament $tournament, Delegation $delegation): View
    {
        $this->assertTournament($tournament);
        abort_unless((int) $delegation->tournament_id === (int) $tournament->id, 404);
        $delegation->load(['teams.category', 'delegates.roles'])->loadCount(['teams', 'delegates']);

        $delegateUsers = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasRole('delegado') && $user->canAccessTournament((int) $tournament->id))
            ->values();

        $categories = Category::query()
            ->accessibleTo($request->user())
            ->where('tournament_id', $tournament->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('workspace.club', [
            'tournament' => $tournament,
            'category' => null,
            'club' => $delegation,
            'categories' => $categories,
            'delegateUsers' => $delegateUsers,
            'assignedDelegate' => $delegation->principalDelegate(),
            'title' => $delegation->name,
            'active' => 'Delegaciones',
            'canEdit' => $this->canEdit($request->user()),
        ]);
    }
}
