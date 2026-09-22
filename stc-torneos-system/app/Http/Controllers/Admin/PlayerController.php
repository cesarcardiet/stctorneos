<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Delegation;
use App\Models\FixtureMatch;
use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Tournament;
use App\Services\GuardianInvitationService;
use App\Services\PlayerDocumentReviewService;
use App\Models\Team;
use App\Support\AdminContext;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Support\Countries;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tournamentId = AdminContext::resolveTournamentId($request);
        if ($tournamentId && ! $request->filled('tournament_id')) {
            $request->merge(['tournament_id' => $tournamentId]);
        }

        $filters = AdminContext::applyTournamentFilter($request, [
            'team_id' => $request->string('team_id')->toString(),
            'category_id' => $request->string('category_id')->toString(),
            'delegation_id' => $request->string('delegation_id')->toString(),
            'tournament_id' => $request->string('tournament_id')->toString(),
            'status' => $request->string('status', 'all')->toString(),
            'documentation' => $request->string('documentation', 'all')->toString(),
            'eligibility' => $request->string('eligibility', 'all')->toString(),
            'search' => $request->string('search')->toString(),
        ]);
        $scopedTournamentId = (int) ($filters['tournament_id'] ?: $tournamentId ?: 0);

        $playersQuery = Player::query()->with(['team.category', 'team.delegation', 'team.tournament', 'guardian']);
        $this->applyFilters($request, $playersQuery);
        $players = $playersQuery->orderBy('last_name')->orderBy('first_name')->paginate(50)->withQueryString();

        $selectedTeam = $request->filled('team_id')
            ? Team::query()->accessibleTo($user)->with('tournament')->find($request->integer('team_id'))
            : null;
        $statsQuery = Player::query()->with(['team']);
        $this->applyFilters($request, $statsQuery);

        $listOpen = $selectedTeam
            ? $selectedTeam->isRosterOpen()
            : (clone $statsQuery)->whereNotIn('status', ['enabled', 'approved', 'blocked'])->exists();

        $stats = [
            [(clone $statsQuery)->count(), 'Jugadores en lista', 'cyan'],
            [(clone $statsQuery)->whereIn('status', ['enabled', 'approved'])->count(), 'Habilitados', 'cyan'],
            [(clone $statsQuery)->whereIn('status', ['pending', 'observed', 'submitted', 'in_progress'])->count(), 'En revisión', 'yellow'],
            [(clone $statsQuery)->where('status', 'blocked')->count(), 'Bloqueados', 'red'],
            [$listOpen ? 'Abierta' : 'Cerrada', 'Estado lista', 'gray'],
        ];

        $selectedCategory = $filters['category_id'] !== '' ? Category::find($filters['category_id']) : null;
        $tournament = $scopedTournamentId > 0 ? Tournament::query()->find($scopedTournamentId) : null;

        return view('admin.players.index', [
            'players' => $players,
            'stats' => $stats,
            'filters' => $filters,
            'selectedTeam' => $selectedTeam,
            'tournament' => $tournament,
            'teams' => Team::query()
                ->accessibleTo($user)
                ->with(['category', 'delegation'])
                ->when($scopedTournamentId > 0, fn ($query) => $query->where('tournament_id', $scopedTournamentId))
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'name', 'category_id', 'delegation_id', 'tournament_id']),
            'categories' => Category::query()
                ->accessibleTo($user)
                ->when($scopedTournamentId > 0, fn ($query) => $query->where('tournament_id', $scopedTournamentId))
                ->orderBy('birth_year')
                ->orderBy('name')
                ->get(['id', 'name', 'birth_year', 'tournament_id']),
            'delegations' => Delegation::query()
                ->accessibleTo($user)
                ->when($scopedTournamentId > 0, fn ($query) => $query->where('tournament_id', $scopedTournamentId))
                ->orderBy('name')
                ->get(['id', 'name', 'tournament_id']),
            'rosterTitle' => 'Plantel declarado'.($selectedTeam ? ' - '.$selectedTeam->name : '').($selectedCategory ? ' - Categoría '.$selectedCategory->birth_year : ''),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $playersQuery = Player::query()->with(['team.category', 'documents']);
        $this->applyFilters($request, $playersQuery);
        $players = $playersQuery->orderBy('last_name')->get();

        return response()->streamDownload(function () use ($players) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['#', 'Jugador', 'Documento', 'Equipo', 'Categoría', 'Estado ficha', 'Documentación', 'Habilitación']);

            foreach ($players as $index => $player) {
                fputcsv($handle, [
                    $index + 1,
                    $player->fullName(),
                    $player->formattedDocument(),
                    $player->team?->name,
                    $player->team?->category?->birth_year,
                    $player->fileStatusLabel(),
                    $player->documentationSummary(),
                    $player->eligibilityLabel(),
                ]);
            }

            fclose($handle);
        }, 'lista-buena-fe.csv');
    }

    public function validateRoster(Request $request): RedirectResponse
    {
        $playersQuery = Player::query()->with('documents');
        $this->applyFilters($request, $playersQuery);
        $players = $playersQuery->get();
        $complete = $players->filter(fn (Player $player) => $player->documentationSummary() === 'Completa')->count();

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Jugadores',
            'action' => 'validate_roster',
            'description' => "Cumplimiento validado: {$complete} fichas completas de {$players->count()}.",
            'auditable_type' => Player::class,
            'auditable_id' => null,
            'metadata' => ['players' => $players->count(), 'complete' => $complete],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return back()->with('status', 'Cumplimiento validado para el plantel filtrado.');
    }

    public function updateRoster(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->isSuperAdmin() || $request->user()?->hasRole('admin-torneo'),
            403,
            'Solo AG o AT pueden reabrir o cerrar la lista.'
        );

        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'roster_open' => ['required', 'boolean'],
        ]);

        $team = Team::findOrFail($data['team_id']);
        $this->assertTeamAccess($team);
        $team->update(['roster_open' => $request->boolean('roster_open')]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Jugadores',
            'action' => $request->boolean('roster_open') ? 'reopen_roster' : 'close_roster',
            'description' => ($request->boolean('roster_open') ? 'Lista de Buena Fe reabierta' : 'Lista de Buena Fe cerrada').' para '.$team->name.'.',
            'auditable_type' => Team::class,
            'auditable_id' => $team->id,
            'metadata' => ['roster_open' => $request->boolean('roster_open')],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        $message = $request->boolean('roster_open')
            ? 'Lista de Buena Fe reabierta. AG/AT pueden volver a modificarla.'
            : 'Lista de Buena Fe cerrada. El Delegado no puede modificarla libremente.';

        return back()->with('status', $message);
    }

    public function create(Request $request): View
    {
        $teamId = $request->integer('team_id') ?: ($request->filled('team') ? $request->integer('team') : null);

        return view('admin.players.form', [
            'player' => new Player([
                'team_id' => $teamId,
                'status' => 'draft',
                'nationality' => 'Argentina',
                'position' => 'Mediocampista',
            ]),
            'guardian' => new Guardian(['relationship' => 'Madre']),
            'method' => 'POST',
            'title' => 'Crear ficha',
            'teams' => Team::query()->accessibleTo($request->user())->with(['category', 'delegation'])->orderBy('name')->get(),
            'url' => route('admin.players.store'),
            'playerCount' => Player::count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPlayer($request);
        $this->assertTeamAccess(Team::findOrFail($data['team_id']));
        $player = Player::create($data);
        $this->syncRelated($request, $player);
        $this->audit('create', $player, 'Jugador creado desde Admin Web.');

        return redirect()->route('admin.players.show', $player)->with('status', 'Jugador creado correctamente.');
    }

    public function show(Request $request, Player $player): View
    {
        $player->load(['team.category', 'team.delegation', 'team.tournament', 'guardian', 'documents', 'invitations']);
        $player->ensureDocuments();
        $this->assertPlayerAccess($player);

        $defaultTab = $request->user()?->hasPermission('players.approve')
            && ($player->status !== 'enabled' || $player->documentationSummary() !== 'Completa')
            ? 'aprobacion'
            : 'resumen';
        $tab = $request->string('tab', $defaultTab)->toString();
        if ($tab === 'documentacion') {
            $tab = 'aprobacion';
        }

        $history = $this->playerHistory($player);
        $matchCount = count($history);
        $goalCount = collect($history)->sum(fn (array $row) => (int) ($row['goals'] ?? 0));
        $canApprove = (bool) $request->user()?->hasPermission('players.approve');

        return view('admin.players.show', compact('player', 'tab', 'history', 'matchCount', 'goalCount', 'canApprove'));
    }

    public function edit(Request $request, Player $player): View
    {
        $this->assertPlayerAccess($player);
        $player->load(['guardian', 'documents', 'invitations', 'team.category', 'team.delegation']);

        return view('admin.players.form', [
            'player' => $player,
            'guardian' => $player->guardian ?? new Guardian(['relationship' => 'Madre']),
            'method' => 'PUT',
            'title' => 'Revisar / Editar Ficha',
            'teams' => Team::query()->accessibleTo($request->user())->with(['category', 'delegation'])->orderBy('name')->get(),
            'url' => route('admin.players.update', $player),
            'playerCount' => Player::count(),
        ]);
    }

    public function update(Request $request, Player $player): RedirectResponse
    {
        $this->assertPlayerAccess($player);
        $data = $this->validatedPlayer($request, $player);
        $this->assertTeamAccess(Team::findOrFail($data['team_id']));
        $player->update($data);
        $this->syncRelated($request, $player);
        $this->audit('update', $player, 'Jugador actualizado desde Admin Web.');

        return redirect()->route('admin.players.edit', $player)->with('status', 'Cambios guardados en la ficha.');
    }

    public function updateStatus(Request $request, Player $player): RedirectResponse
    {
        $this->assertPlayerAccess($player);
        $data = $request->validate(['status' => ['required', 'string', 'in:draft,awaiting_guardian,in_progress,submitted,pending,observed,approved,rejected,enabled,blocked']]);
        $player->update($data);
        $this->audit('status_update', $player, 'Estado de ficha de jugador actualizado.');

        return back()->with('status', 'Estado de la ficha actualizado.');
    }

    public function reviewDocument(Request $request, Player $player, PlayerDocument $document, PlayerDocumentReviewService $reviews): RedirectResponse
    {
        $this->assertPlayerAccess($player);
        abort_unless((int) $document->player_id === (int) $player->id, 404);
        abort_unless($request->user()?->hasPermission('players.approve'), 403);

        $reviews->update(
            $document,
            $reviews->validatedReviewPayload($request),
            $request->user()
        );

        return redirect()
            ->route('admin.players.show', [$player, 'tab' => 'aprobacion'])
            ->with('status', $document->type.' quedó '.$document->statusLabel().'.');
    }

    public function enable(Request $request, Player $player, PlayerDocumentReviewService $reviews): RedirectResponse
    {
        $this->assertPlayerAccess($player);
        abort_unless($request->user()?->hasPermission('players.approve'), 403);

        $reviews->enablePlayer($player, $request->user());

        return redirect()
            ->route('admin.players.show', [$player, 'tab' => 'aprobacion'])
            ->with('status', $player->fullName().' quedó habilitado para jugar.');
    }

    public function review(Request $request, Player $player): RedirectResponse
    {
        $this->assertPlayerAccess($player);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,awaiting_guardian,in_progress,submitted,pending,observed,approved,rejected,enabled,blocked'],
            'consent_status' => ['nullable', 'string', 'in:pending,approved,rejected'],
        ]);

        $player->update(['status' => $data['status']]);

        if ($player->guardian && $request->filled('consent_status')) {
            $player->guardian->update(['consent_status' => $data['consent_status']]);
        }

        $this->audit('review', $player, 'Revisión de jugador desde plantel: '.$player->fullName());

        return back()->with('status', 'Revisión de '.$player->fullName().' guardada.');
    }

    public function destroy(Player $player): RedirectResponse
    {
        $this->assertPlayerAccess($player);
        $this->audit('delete', $player, 'Jugador eliminado desde Admin Web.');
        $player->delete();

        return redirect()->route('admin.players.index')->with('status', 'Jugador eliminado correctamente.');
    }

    public function inviteGuardian(Request $request, Player $player): RedirectResponse
    {
        $this->assertPlayerAccess($player);
        $player->load('guardian');

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:180'],
        ]);

        $email = $data['email'] ?: $player->guardian?->email;
        abort_unless($email, 422, 'Cargá un correo del tutor para generar el enlace.');

        $invitation = app(GuardianInvitationService::class)->create(
            $player,
            $email,
            auth()->id()
        );

        $this->audit('invite', $player, 'Invitación al tutor generada: '.$invitation->email);

        return back()->with('status', 'Enlace de tutor generado y enviado a '.$invitation->email.'.');
    }

    public function regenerateInvite(Player $player): RedirectResponse
    {
        return $this->inviteGuardian(request()->merge([
            'email' => $player->latestGuardianInvitation()?->email ?: $player->guardian?->email,
        ]), $player);
    }

    public function invalidateInvite(Player $player): RedirectResponse
    {
        $this->assertPlayerAccess($player);
        app(GuardianInvitationService::class)->invalidate($player);
        $this->audit('invite_invalidate', $player, 'Invitación al tutor invalidada.');

        return back()->with('status', 'El enlace del tutor quedó invalidado.');
    }

    public function credential(Player $player): View
    {
        $this->assertPlayerAccess($player);
        $player->load(['team.category', 'team.tournament', 'team.delegation']);
        $payload = route('admin.players.credential', $player);
        $qrUrl = QrCode::url($payload);

        return view('admin.players.credential', compact('player', 'qrUrl'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPlayer(Request $request, ?Player $player = null): array
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date', 'after_or_equal:1990-01-01', 'before_or_equal:today'],
            'nationality' => ['required', 'string', 'max:80', Rule::in(array_values(Countries::all()))],
            'position' => ['nullable', 'string', 'max:80'],
            'jersey_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'kit_size' => ['nullable', 'string', Rule::in(Player::kitSizes())],
            'photo_path' => ['nullable', 'string', 'max:500'],
            'photo_file' => ['nullable', 'image', 'max:4096'],
            'status' => ['required', 'string', 'in:draft,awaiting_guardian,in_progress,submitted,pending,observed,approved,rejected,enabled,blocked'],
            'address' => ['nullable', 'string', 'max:255'],
            'preferred_foot' => ['nullable', 'string', 'max:40'],
            'height' => ['nullable', 'string', 'max:40'],
            'weight' => ['nullable', 'string', 'max:40'],
            'blood_type' => ['nullable', 'string', 'max:20'],
            'medical_coverage' => ['nullable', 'string', 'max:120'],
            'allergies' => ['nullable', 'string', 'max:180'],
            'medication' => ['nullable', 'string', 'max:180'],
            'illnesses' => ['nullable', 'string', 'max:180'],
            'restrictions' => ['nullable', 'string', 'max:180'],
            'emergency_contact' => ['nullable', 'string', 'max:180'],
            'observation_reason' => ['nullable', 'string', 'max:255'],
            'medical_notes' => ['nullable', 'string', 'max:1200'],
            'vaccination_calendar_complete' => ['nullable', 'in:0,1,'],
            'ongoing_treatment' => ['nullable', 'in:0,1,'],
            'ongoing_treatment_notes' => ['nullable', 'string', 'max:1200'],
            'notes' => ['nullable', 'string', 'max:1200'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_document_number' => ['nullable', 'string', 'max:40'],
            'guardian_relationship' => ['nullable', 'string', 'max:80'],
            'guardian_email' => ['nullable', 'email', 'max:180'],
            'guardian_phone' => ['nullable', 'string', 'max:80'],
            'guardian_alternate_contact' => ['nullable', 'string', 'max:255'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['string'],
            'photo_file_status' => ['nullable', 'string'],
        ]);

        unset(
            $data['photo_file'],
            $data['photo_file_status'],
            $data['guardian_name'],
            $data['guardian_document_number'],
            $data['guardian_relationship'],
            $data['guardian_email'],
            $data['guardian_phone'],
            $data['guardian_alternate_contact'],
            $data['documents'],
        );

        if ($request->hasFile('photo_file')) {
            $file = $request->file('photo_file');
            $directory = public_path('images/players');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                .'-'.Str::random(8).'.'.$file->getClientOriginalExtension();

            File::ensureDirectoryExists($directory);
            $file->move($directory, $filename);
            $data['photo_path'] = 'images/players/'.$filename;
        } elseif ($player?->photo_path) {
            $data['photo_path'] = $player->photo_path;
        }

        if ($request->exists('vaccination_calendar_complete')) {
            $value = $request->input('vaccination_calendar_complete');
            $data['vaccination_calendar_complete'] = $value === '' || $value === null
                ? null
                : $request->boolean('vaccination_calendar_complete');
        }

        if ($request->exists('ongoing_treatment')) {
            $value = $request->input('ongoing_treatment');
            $data['ongoing_treatment'] = $value === '' || $value === null
                ? null
                : $request->boolean('ongoing_treatment');
            $data['ongoing_treatment_notes'] = $data['ongoing_treatment'] === true
                ? ($data['ongoing_treatment_notes'] ?? null)
                : null;
        }

        return $data;
    }

    private function syncRelated(Request $request, Player $player): void
    {
        if ($request->filled('guardian_name')) {
            Guardian::updateOrCreate(
                ['player_id' => $player->id],
                [
                    'name' => $request->string('guardian_name')->toString(),
                    'document_number' => $request->input('guardian_document_number'),
                    'relationship' => $request->string('guardian_relationship', 'Madre')->toString(),
                    'email' => $request->input('guardian_email'),
                    'phone' => $request->input('guardian_phone'),
                    'alternate_contact' => $request->input('guardian_alternate_contact'),
                ]
            );
        }

        $approved = $request->input('documents', []);

        foreach (Player::documentTypes() as $type) {
            PlayerDocument::updateOrCreate(
                ['player_id' => $player->id, 'type' => $type],
                ['status' => in_array($type, $approved, true) ? 'approved' : 'pending']
            );
        }
    }

    private function audit(string $action, Player $player, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Jugadores',
            'action' => $action,
            'description' => $description,
            'auditable_type' => Player::class,
            'auditable_id' => $player->id,
            'metadata' => ['name' => $player->fullName()],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function applyFilters(Request $request, $query): void
    {
        $user = $request->user();

        $query
            ->when(
                $user?->restrictsToAssignedClub() || ! $user?->canAccessAllTournaments(),
                fn ($query) => $query->whereHas('team', fn ($team) => $team->accessibleTo($user))
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhereHas('team', fn ($query) => $query->where('name', 'like', "%{$search}%")));
            })
            ->when($request->filled('team_id'), fn ($query) => $query->where('team_id', $request->integer('team_id')))
            ->when($request->filled('delegation_id'), fn ($query) => $query->whereHas('team', fn ($query) => $query->where('delegation_id', $request->integer('delegation_id'))))
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas('team', fn ($query) => $query->where('category_id', $request->integer('category_id'))))
            ->when($request->filled('tournament_id'), fn ($query) => $query->whereHas('team', fn ($query) => $query->where('tournament_id', $request->integer('tournament_id'))))
            ->when($request->filled('status') && $request->string('status')->toString() !== 'all', function ($query) use ($request) {
                $status = $request->string('status')->toString();

                if ($status === 'review') {
                    $query->whereIn('status', ['draft', 'awaiting_guardian', 'in_progress', 'submitted', 'pending', 'observed']);

                    return;
                }

                $query->where('status', $status);
            })
            ->when($request->filled('eligibility') && $request->string('eligibility')->toString() !== 'all', function ($query) use ($request) {
                $request->string('eligibility')->toString() === 'enabled'
                    ? $query->where('status', 'enabled')
                    : $query->where('status', '!=', 'enabled');
            })
            ->when($request->filled('documentation') && $request->string('documentation')->toString() !== 'all', function ($query) use ($request) {
                $documentation = $request->string('documentation')->toString();

                if ($documentation === 'complete') {
                    $query->has('documents', '>=', 3)
                        ->whereDoesntHave('documents', fn ($docs) => $docs->where('status', '!=', 'approved'));

                    return;
                }

                if ($documentation === 'observed') {
                    $query->whereHas('documents', fn ($docs) => $docs->where('status', 'observed'));

                    return;
                }

                $query->where(function ($query) {
                    $query->has('documents', '<', 3)
                        ->orWhereHas('documents', fn ($docs) => $docs->whereIn('status', ['pending', 'observed', 'rejected']));
                });
            });
    }

    private function assertPlayerAccess(Player $player): void
    {
        if ($player->team) {
            $this->assertTeamAccess($player->team);
        }
    }

    private function assertTeamAccess(Team $team): void
    {
        abort_unless(
            Team::query()->accessibleTo(auth()->user())->whereKey($team->id)->exists(),
            403,
            'Este plantel no está dentro de tu alcance.'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function playerHistory(Player $player): array
    {
        if (! $player->team_id) {
            return [];
        }

        return FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where(fn ($query) => $query->where('home_team_id', $player->team_id)->orWhere('away_team_id', $player->team_id))
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get()
            ->map(function (FixtureMatch $match) use ($player) {
                $isHome = (int) $match->home_team_id === (int) $player->team_id;
                $rival = $isHome ? $match->awayTeam : $match->homeTeam;
                $ownScore = (int) ($isHome ? $match->home_score : $match->away_score);
                $goals = in_array($match->status, ['finished', 'live'], true)
                    ? ($player->position === 'Delantero' ? min(2, max(0, $ownScore)) : ($player->position === 'Mediocampista' ? min(1, $ownScore) : 0))
                    : 0;

                return [
                    'date' => $match->scheduled_at?->format('d M') ?? '—',
                    'rival' => $rival?->name ?? 'Rival',
                    'score' => ((int) $match->home_score).'-'.((int) $match->away_score),
                    'events' => $goals > 0 ? $goals.' '.($goals === 1 ? 'gol' : 'goles') : 'Sin eventos',
                    'goals' => $goals,
                ];
            })
            ->all();
    }
}
