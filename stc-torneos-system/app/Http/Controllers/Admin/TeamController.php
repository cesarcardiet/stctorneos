<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\QrCode;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Delegation;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Support\AdminContext;
use App\Support\ShieldPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $accessibleTournaments = $user->accessibleTournaments();

        $filters = AdminContext::applyTournamentFilter($request, [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status', 'all')->toString(),
            'tournament_id' => $request->string('tournament_id')->toString(),
            'delegation_id' => $request->string('delegation_id')->toString(),
            'category_id' => $request->string('category_id')->toString(),
        ]);

        $allTeams = Team::query()
            ->accessibleTo($user)
            ->with(['delegation', 'category'])
            ->withCount('players')
            ->get();

        $teams = Team::query()
            ->accessibleTo($user)
            ->with(['delegation', 'category', 'tournament'])
            ->withCount([
                'players',
                'players as enabled_players_count' => fn ($query) => $query->where('status', 'enabled'),
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('delegation_name', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%")
                    ->orWhereHas('delegation', fn ($delegation) => $delegation->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%")));
            })
            ->when($filters['status'] !== '' && $filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['tournament_id'] !== '', function ($query) use ($filters, $user) {
                $tournamentId = (int) $filters['tournament_id'];
                abort_unless($user->canAccessTournament($tournamentId), 403, 'Este torneo no está dentro de tu alcance.');
                $query->where('tournament_id', $tournamentId);
            })
            ->when($filters['delegation_id'] !== '', fn ($query) => $query->where('delegation_id', (int) $filters['delegation_id']))
            ->when($filters['category_id'] !== '', fn ($query) => $query->where('category_id', (int) $filters['category_id']))
            ->latest()
            ->get();

        $stats = [
            ['Equipos', $allTeams->count(), 'blue'],
            ['Con cupo OK', $allTeams->filter(fn (Team $team) => (int) $team->players_count >= max(1, (int) $team->player_capacity))->count(), 'green'],
            ['Incompletos', $allTeams->filter(fn (Team $team) => $team->status !== 'blocked' && (int) $team->players_count < max(1, (int) $team->player_capacity))->count(), 'yellow'],
            ['Bloqueados', $allTeams->where('status', 'blocked')->count(), 'red'],
        ];

        $subheading = 'Planteles por delegación, categoría, estado competitivo y cupos.';

        return view('admin.teams.index', [
            'teams' => $teams,
            'stats' => $stats,
            'filters' => $filters,
            'accessibleTournaments' => $accessibleTournaments,
            'delegations' => Delegation::query()->accessibleTo($user)->orderBy('name')->get(),
            'categories' => Category::query()->accessibleTo($user)->orderBy('name')->get(),
            'subheading' => $subheading,
        ]);
    }

    public function create(Request $request): View
    {
        $delegation = null;
        if ($request->filled('delegation')) {
            $delegation = Delegation::query()->accessibleTo($request->user())->findOrFail($request->integer('delegation'));
        }

        return view('admin.teams.form', [
            'team' => new Team([
                'tournament_id' => $delegation?->tournament_id,
                'delegation_id' => $delegation?->id,
                'delegation_name' => $delegation?->name,
                'city' => $delegation?->city,
                'status' => 'pending',
                'player_capacity' => 14,
                'home_kit' => 'Azul / Rojo',
                'away_kit' => 'Blanca',
            ]),
            'method' => 'POST',
            'title' => 'Crear / Editar Equipo',
            'url' => route('admin.teams.store'),
            ...$this->formLookups($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = ShieldPayload::fromRequest($request);
        $team = Team::create($this->validatedData($request));
        $team->attachClub($team->delegation_id, $team->delegation_name);
        if ($payload) {
            $team->storeClubShield($payload[0], $payload[1]);
        }
        $this->audit('create', $team, 'Equipo creado desde Admin Web.');

        return redirect()->route('admin.teams.show', $team)->with('status', 'Equipo creado correctamente.');
    }

    public function show(Team $team): View
    {
        $this->assertTeamAccess($team);

        $team->load(['tournament', 'category', 'delegation', 'staffMembers', 'players.documents'])->loadCount('players');
        $matches = $team->allMatches();
        $upcoming = $matches->whereIn('status', ['scheduled', 'live', 'rescheduled'])->values();
        $results = $matches->whereIn('status', ['finished', 'validated', 'live'])->sortByDesc('scheduled_at')->values();
        $stats = $team->matchStats();
        $availableRoles = collect(TeamStaff::roleLabels())
            ->reject(fn ($label, $role) => $team->staffMembers->contains(fn (TeamStaff $member) => $member->role === $role && $member->isActive()))
            ->all();

        return view('admin.teams.show', compact('team', 'upcoming', 'results', 'stats', 'availableRoles'));
    }

    public function edit(Request $request, Team $team): View
    {
        $this->assertTeamAccess($team);

        $team->load(['delegation', 'category'])->loadCount('players');

        return view('admin.teams.form', [
            'team' => $team,
            'method' => 'PUT',
            'title' => 'Crear / Editar Equipo',
            'url' => route('admin.teams.update', $team),
            ...$this->formLookups($request),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->assertTeamAccess($team);
        $payload = ShieldPayload::fromRequest($request);
        $team->update($this->validatedData($request));
        $team->attachClub($team->delegation_id, $team->delegation_name);
        if ($payload) {
            $team->storeClubShield($payload[0], $payload[1]);
        }
        $this->audit('update', $team, 'Equipo actualizado desde Admin Web.');

        return redirect()->route('admin.teams.show', $team)->with('status', 'Equipo actualizado correctamente.');
    }

    public function updateStatus(Request $request, Team $team): RedirectResponse
    {
        $this->assertTeamAccess($team);
        $data = $request->validate(['status' => ['required', 'string', 'in:approved,pending,observed,blocked']]);
        $team->update($data);
        $this->audit('status_update', $team, 'Estado de equipo actualizado.');

        return back()->with('status', 'Estado del equipo actualizado.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->assertTeamAccess($team);
        $showUrl = route('admin.teams.show', $team);
        $editUrl = route('admin.teams.edit', $team);
        $listUrl = route('admin.teams.index');
        $this->audit('delete', $team, 'Equipo eliminado desde Admin Web.');
        $team->delete();

        $previous = url()->previous($listUrl);
        $previousPath = rtrim((string) (parse_url($previous, PHP_URL_PATH) ?: $previous), '/');
        $avoid = [
            rtrim((string) parse_url($showUrl, PHP_URL_PATH), '/'),
            rtrim((string) parse_url($editUrl, PHP_URL_PATH), '/'),
        ];

        $target = in_array($previousPath, $avoid, true) ? $listUrl : $previous;

        return redirect()->to($target)->with('status', 'Equipo eliminado correctamente.');
    }

    public function createStaff(Team $team): View
    {
        $this->assertTeamAccess($team);

        return view('admin.teams.staff', [
            'team' => $team,
            'member' => new TeamStaff(['status' => 'active']),
            'method' => 'POST',
            'title' => 'Agregar cuerpo técnico',
            'url' => route('admin.teams.staff.store', $team),
            'roles' => $this->availableRoles($team),
        ]);
    }

    public function storeStaff(Request $request, Team $team): RedirectResponse
    {
        $this->assertTeamAccess($team);
        $team->staffMembers()->create($this->validatedStaff($request, $team));
        $this->audit('staff_create', $team, 'Integrante de cuerpo técnico agregado.');

        return redirect()->route('admin.teams.show', $team)->with('status', 'Cuerpo técnico actualizado.');
    }

    public function editStaff(Team $team, TeamStaff $staff): View
    {
        $this->assertTeamAccess($team);
        abort_unless((int) $staff->team_id === (int) $team->id, 404);

        return view('admin.teams.staff', [
            'team' => $team,
            'member' => $staff,
            'method' => 'PUT',
            'title' => 'Editar cuerpo técnico',
            'url' => route('admin.teams.staff.update', [$team, $staff]),
            'roles' => $this->availableRoles($team, $staff),
        ]);
    }

    public function updateStaff(Request $request, Team $team, TeamStaff $staff): RedirectResponse
    {
        $this->assertTeamAccess($team);
        abort_unless((int) $staff->team_id === (int) $team->id, 404);
        $staff->update($this->validatedStaff($request, $team, $staff));
        $this->audit('staff_update', $team, 'Cuerpo técnico actualizado: '.$staff->fullName().'.');

        return redirect()->route('admin.teams.show', $team)->with('status', 'Cuerpo técnico actualizado.');
    }

    public function withdrawStaff(Team $team, TeamStaff $staff): RedirectResponse
    {
        $this->assertTeamAccess($team);
        abort_unless((int) $staff->team_id === (int) $team->id, 404);
        $staff->update(['status' => 'withdrawn']);
        $this->audit('staff_withdraw', $team, 'Cuerpo técnico retirado: '.$staff->fullName().'.');

        return back()->with('status', $staff->fullName().' fue retirado del cuerpo técnico.');
    }

    public function credential(Team $team, TeamStaff $staff): View
    {
        $this->assertTeamAccess($team);
        abort_unless((int) $staff->team_id === (int) $team->id, 404);
        $team->load(['tournament', 'category', 'delegation']);
        $payload = route('admin.teams.staff.credential', [$team, $staff]);
        $qrUrl = QrCode::url($payload);

        return view('admin.teams.credential', compact('team', 'staff', 'qrUrl'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'delegation_id' => ['nullable', 'exists:delegations,id'],
            'name' => ['required', 'string', 'max:180'],
            'delegation_name' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(\App\Support\Countries::codes())],
            'group_name' => ['nullable', 'string', 'max:20'],
            'home_kit' => ['nullable', 'string', 'max:120'],
            'away_kit' => ['nullable', 'string', 'max:120'],
            'player_capacity' => ['required', 'integer', 'min:1', 'max:40'],
            'shield_path' => ['nullable', 'string', 'max:500'],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
            'notes' => ['nullable', 'string', 'max:1200'],
            'status' => ['required', 'string', 'in:approved,pending,observed,blocked'],
        ]);

        abort_unless(
            $request->user()->canAccessTournament((int) $data['tournament_id']),
            403,
            'Este torneo no está dentro de tu alcance.'
        );

        $category = Category::findOrFail($data['category_id']);
        abort_unless(
            $request->user()->canAccessTournament((int) $category->tournament_id),
            403,
            'Esta categoría no está dentro de tu alcance.'
        );

        unset($data['shield_file'], $data['shield_data']);

        if ($data['delegation_id'] ?? null) {
            $delegation = Delegation::findOrFail($data['delegation_id']);
            abort_unless(
                $request->user()->canAccessTournament((int) $delegation->tournament_id),
                403,
                'Esta delegación no está dentro de tu alcance.'
            );
            $data['delegation_name'] = $delegation->name;
            $data['city'] = $data['city'] ?: $delegation->city;
        }

        $data['delegation_name'] = $data['delegation_name'] ?: 'Sin delegación';

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedStaff(Request $request, Team $team, ?TeamStaff $staff = null): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:40'],
            'role' => [
                'required',
                Rule::in(array_keys(TeamStaff::roleLabels())),
                Rule::unique('team_staff', 'role')
                    ->where(fn ($query) => $query->where('team_id', $team->id)->where('status', 'active'))
                    ->ignore($staff?->id),
            ],
            'photo_path' => ['nullable', 'string', 'max:500'],
            'photo_file' => ['nullable', 'image', 'max:4096'],
            'status' => ['required', 'string', 'in:active,withdrawn'],
        ]);

        unset($data['photo_file']);

        if ($request->hasFile('photo_file')) {
            $file = $request->file('photo_file');
            $directory = public_path('images/staff');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                .'-'.Str::random(8).'.'.$file->getClientOriginalExtension();

            File::ensureDirectoryExists($directory);
            $file->move($directory, $filename);
            $data['photo_path'] = 'images/staff/'.$filename;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formLookups(Request $request): array
    {
        $user = $request->user();

        return [
            'tournaments' => $user->accessibleTournaments(),
            'categories' => Category::query()->accessibleTo($user)->orderBy('name')->get(),
            'delegations' => Delegation::query()->accessibleTo($user)->orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function availableRoles(Team $team, ?TeamStaff $current = null): array
    {
        $taken = $team->staffMembers()
            ->where('status', 'active')
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
            ->pluck('role');

        return collect(TeamStaff::roleLabels())
            ->reject(fn ($label, $role) => $taken->contains($role))
            ->all();
    }

    private function assertTeamAccess(Team $team): void
    {
        abort_unless(
            auth()->user()?->canAccessTournament((int) $team->tournament_id),
            403,
            'Este equipo no está dentro de tu alcance.'
        );
    }

    private function audit(string $action, Team $team, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Equipos',
            'action' => $action,
            'description' => $description,
            'auditable_type' => Team::class,
            'auditable_id' => $team->id,
            'metadata' => ['name' => $team->name],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
