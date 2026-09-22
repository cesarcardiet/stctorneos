<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\MatchLineup;
use App\Models\MatchPenaltyKick;
use App\Models\Team;
use App\Models\User;
use App\Services\FixtureGenerator;
use App\Support\AdminContext;
use App\Support\DateTimeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FixtureController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tournamentId = AdminContext::resolveTournamentId($request);
        if ($tournamentId && ! $request->filled('tournament_id')) {
            $request->merge(['tournament_id' => $tournamentId]);
        }

        $query = FixtureMatch::query()
            ->accessibleTo($user)
            ->with([
                'tournament',
                'category',
                'field.venue',
                'homeTeam.delegation',
                'awayTeam.delegation',
            ]);
        $this->applyFilters($request, $query);
        $matches = $query->orderBy('scheduled_at')->get();

        $accessibleMatches = FixtureMatch::query()
            ->accessibleTo($user)
            ->with(['homeTeam', 'awayTeam', 'field'])
            ->get();
        $conflicts = FixtureMatch::detectConflicts($accessibleMatches);
        $conflictIds = $conflicts
            ->flatMap(fn (array $conflict) => [$conflict['match']->id, $conflict['other']->id])
            ->unique()
            ->all();

        $statsQuery = FixtureMatch::query()->accessibleTo($user);

        return view('admin.fixture.index', [
            'matches' => $matches,
            'conflicts' => $conflicts,
            'conflictIds' => $conflictIds,
            'stats' => [
                [(clone $statsQuery)->count(), 'Partidos', 'cyan'],
                [(clone $statsQuery)->where('status', 'scheduled')->count(), 'Programados', 'blue'],
                [$conflicts->count(), 'Conflictos', 'orange'],
                [(clone $statsQuery)->where('published', false)->whereIn('status', ['scheduled', 'rescheduled'])->count(), 'Pendientes', 'yellow'],
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status', 'all')->toString(),
                'category_id' => $request->string('category_id')->toString(),
                'tournament_id' => $request->string('tournament_id')->toString(),
                'round' => $request->string('round')->toString(),
                'date' => $request->string('date')->toString(),
                'team_id' => $request->string('team_id')->toString(),
                'field_id' => $request->string('field_id')->toString(),
            ],
            'rounds' => FixtureMatch::query()->accessibleTo($user)->whereNotNull('round')->distinct()->orderBy('round')->pluck('round'),
            'categories' => Category::query()
                ->accessibleTo($user)
                ->when($tournamentId, fn ($query) => $query->where('tournament_id', $tournamentId))
                ->orderBy('birth_year')
                ->orderBy('name')
                ->get(),
            'accessibleTournaments' => $user->accessibleTournaments(),
            'teams' => Team::query()->accessibleTo($user)->orderBy('name')->get(),
            'fields' => Field::query()->accessibleTo($user)->with('venue')->orderBy('name')->get(),
            'subheading' => $user->canAccessAllTournaments()
                ? 'Programación por día, categoría, cancha y equipo.'
                : 'Fixture de tus torneos asignados.',
        ]);
    }

    public function create(Request $request): View
    {
        $tournamentId = $request->integer('tournament_id') ?: $request->user()?->accessibleTournaments()->first()?->id;

        return $this->form(new FixtureMatch([
            'tournament_id' => $tournamentId,
            'category_id' => $request->integer('category_id') ?: null,
            'field_id' => $request->integer('field_id') ?: null,
            'status' => 'scheduled',
            'stage' => 'Grupo A',
            'round' => 'Fecha 1',
            'duration_minutes' => 70,
            'published' => false,
            'scheduled_at' => now()->setTime(9, 0),
        ]), 'POST', route('admin.fixture.store'), 'Programar partido');
    }

    public function store(Request $request): RedirectResponse
    {
        $match = FixtureMatch::create($this->validatedData($request));
        $this->audit($match, 'create', 'Partido programado: '.$match->title());

        return redirect()
            ->route('admin.fixture.show', $match)
            ->with('status', 'Partido programado correctamente.');
    }

    public function jornada(Request $request): View
    {
        $user = $request->user();
        $date = $request->string('date')->toString() ?: now()->toDateString();
        $query = FixtureMatch::query()
            ->accessibleTo($user)
            ->with([
                'tournament',
                'category',
                'field.venue',
                'homeTeam',
                'awayTeam',
                'refereeUser',
                'assistantUser',
            ])
            ->whereDate('scheduled_at', $date);
        $this->applyFilters($request, $query);
        $matches = $query->orderBy('scheduled_at')->get();

        $accessibleMatches = FixtureMatch::query()
            ->accessibleTo($user)
            ->with(['homeTeam', 'awayTeam', 'field'])
            ->whereDate('scheduled_at', $date)
            ->get();
        $conflicts = FixtureMatch::detectConflicts($accessibleMatches);
        $conflictIds = $conflicts
            ->flatMap(fn (array $conflict) => [$conflict['match']->id, $conflict['other']->id])
            ->unique()
            ->all();

        $byField = $matches->groupBy(fn (FixtureMatch $match) => $match->field?->name ?: 'Sin cancha');

        return view('admin.fixture.jornada', [
            'date' => $date,
            'matches' => $matches,
            'byField' => $byField,
            'conflicts' => $conflicts,
            'conflictIds' => $conflictIds,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status', 'all')->toString(),
                'category_id' => $request->string('category_id')->toString(),
                'tournament_id' => $request->string('tournament_id')->toString(),
                'round' => $request->string('round')->toString(),
                'date' => $date,
                'team_id' => $request->string('team_id')->toString(),
                'field_id' => $request->string('field_id')->toString(),
            ],
            'categories' => Category::query()->accessibleTo($user)->orderBy('name')->get(),
            'accessibleTournaments' => $user->accessibleTournaments(),
            'fields' => Field::query()->accessibleTo($user)->with('venue')->orderBy('name')->get(),
            'subheading' => 'Mapa de partidos de la fecha: canchas, horarios, árbitros y conflictos.',
        ]);
    }

    public function show(FixtureMatch $match): View
    {
        $this->assertMatchAccess($match);
        $match->load([
            'tournament',
            'category',
            'field.venue',
            'homeTeam.delegation',
            'awayTeam.delegation',
            'homeTeam.players.documents',
            'awayTeam.players.documents',
            'sheet.events.player.documents',
            'sheet.events.teamStaff',
            'sheet.events.team.delegation',
            'refereeUser',
            'assistantUser',
            'scorerUser',
            'penaltyKicks.player',
            'penaltyKicks.team',
            'lineups.player',
            'nextMatch.homeTeam',
            'nextMatch.awayTeam',
        ]);

        $conflicts = FixtureMatch::detectConflicts(
            FixtureMatch::query()
                ->accessibleTo(request()->user())
                ->with(['homeTeam', 'awayTeam', 'field'])
                ->get()
        )->filter(fn (array $conflict) => $conflict['match']->id === $match->id || $conflict['other']->id === $match->id);

        $officials = $this->officials(request()->user());
        $knockoutMatches = FixtureMatch::query()
            ->accessibleTo(request()->user())
            ->with(['homeTeam', 'awayTeam'])
            ->where('category_id', $match->category_id)
            ->whereKeyNot($match->id)
            ->orderBy('scheduled_at')
            ->get();

        return view('admin.fixture.show', compact('match', 'conflicts', 'officials', 'knockoutMatches'));
    }

    public function edit(FixtureMatch $match): View
    {
        $this->assertMatchAccess($match);

        return $this->form($match, 'PUT', route('admin.fixture.update', $match), 'Editar partido');
    }

    public function update(Request $request, FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);
        $match->update($this->validatedData($request, $match));
        $match->refresh();
        $match->propagateWinner();
        $this->audit($match, 'update', 'Partido actualizado: '.$match->title());

        return redirect()
            ->route('admin.fixture.show', $match)
            ->with('status', 'Partido actualizado correctamente.');
    }

    public function updateStatus(Request $request, FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);
        $data = $request->validate([
            'status' => ['required', 'string', 'in:scheduled,live,paused,finished,suspended,rescheduled,validated,reopened'],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:30'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:30'],
            'minute' => ['nullable', 'string', 'max:12'],
            'period' => ['nullable', 'string', 'in:1t,2t,et,pen'],
            'scorer_user_id' => ['nullable', 'exists:users,id'],
            'referee_user_id' => ['nullable', 'exists:users,id'],
            'assistant_user_id' => ['nullable', 'exists:users,id'],
        ]);

        if (! empty($data['referee_user_id'])) {
            $data['referee_name'] = User::query()->find($data['referee_user_id'])?->name;
        }

        $match->update($data);
        if (in_array($match->status, ['finished', 'validated'], true)) {
            $match->propagateWinner();
        }
        $this->audit($match, 'status_update', 'Estado de partido actualizado a '.$match->statusLabel().'.');

        return back()->with('status', 'Estado del partido actualizado.');
    }

    public function storePenalty(Request $request, FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'player_id' => ['nullable', 'exists:players,id'],
            'scored' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:180'],
        ]);

        abort_unless(in_array((int) $data['team_id'], [(int) $match->home_team_id, (int) $match->away_team_id], true), 422);

        $sequence = (int) $match->penaltyKicks()->max('sequence') + 1;
        MatchPenaltyKick::create([
            'match_id' => $match->id,
            'sequence' => $sequence,
            'team_id' => $data['team_id'],
            'player_id' => $data['player_id'] ?? null,
            'scored' => $request->boolean('scored'),
            'notes' => $data['notes'] ?? null,
        ]);

        $match->update(['period' => 'pen']);
        $match->load('penaltyKicks');
        $match->propagateWinner();
        $this->audit($match, 'penalty', 'Penal cargado en '.$match->title());

        return back()->with('status', 'Tiro de penal registrado.');
    }

    public function destroyPenalty(FixtureMatch $match, MatchPenaltyKick $kick): RedirectResponse
    {
        $this->assertMatchAccess($match);
        abort_unless((int) $kick->match_id === (int) $match->id, 404);
        $kick->delete();
        $match->load('penaltyKicks');
        $match->propagateWinner();
        $this->audit($match, 'penalty_delete', 'Penal eliminado en '.$match->title());

        return back()->with('status', 'Tiro de penal eliminado.');
    }

    public function officialsAgenda(Request $request): View
    {
        $user = $request->user();
        $officials = $this->officials($user);
        $ids = $officials->pluck('id');
        $assigned = FixtureMatch::query()
            ->accessibleTo($user)
            ->with(['homeTeam', 'awayTeam', 'category', 'field.venue', 'refereeUser', 'assistantUser'])
            ->where(function ($query) use ($ids) {
                $query->whereIn('referee_user_id', $ids)->orWhereIn('assistant_user_id', $ids);
            })
            ->orderBy('scheduled_at')
            ->get();

        $rows = $officials->map(function (User $official) use ($assigned) {
            return [
                'official' => $official,
                'matches' => $assigned->filter(fn (FixtureMatch $match) => (int) $match->referee_user_id === (int) $official->id
                    || (int) $match->assistant_user_id === (int) $official->id)->values(),
            ];
        });

        return view('admin.fixture.officials', [
            'rows' => $rows,
            'subheading' => 'Agenda de árbitros y asistentes de mesa con los partidos asignados.',
        ]);
    }

    public function reopen(Request $request, FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);
        abort_unless(
            in_array($match->status, ['finished', 'validated'], true),
            422,
            'Solo se reabre un partido que ya quedó oficial.'
        );

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        $previous = [
            'status' => $match->status,
            'home_score' => $match->home_score,
            'away_score' => $match->away_score,
            'published' => $match->published,
            'period' => $match->period,
            'penalties' => $match->penaltyScore(),
        ];

        $match->update([
            'previous_result' => $previous,
            'reopen_reason' => $data['reason'],
            'status' => 'reopened',
            'published' => false,
            'published_at' => null,
        ]);

        $match->sheet?->update(['locked' => false]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Fixture',
            'action' => 'reopen',
            'description' => 'Partido reabierto: '.$match->title().' · '.$data['reason'],
            'auditable_type' => FixtureMatch::class,
            'auditable_id' => $match->id,
            'metadata' => [
                'reason' => $data['reason'],
                'previous' => $previous,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Partido reabierto. Corregí el resultado y volvé a dejarlo oficial.');
    }

    public function storeLineup(Request $request, FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);
        $data = $request->validate([
            'starters' => ['nullable', 'array'],
            'starters.*' => ['integer', 'exists:players,id'],
        ]);

        $ids = collect($data['starters'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $players = \App\Models\Player::query()->whereIn('id', $ids)->get();
        $allowedTeams = [(int) $match->home_team_id, (int) $match->away_team_id];
        abort_unless($players->every(fn ($player) => in_array((int) $player->team_id, $allowedTeams, true)), 422);
        abort_if($players->where('team_id', $match->home_team_id)->count() > 11, 422, 'El local no puede tener más de 11 titulares.');
        abort_if($players->where('team_id', $match->away_team_id)->count() > 11, 422, 'El visitante no puede tener más de 11 titulares.');

        $match->lineups()->delete();
        foreach ($players as $index => $player) {
            MatchLineup::create([
                'match_id' => $match->id,
                'team_id' => $player->team_id,
                'player_id' => $player->id,
                'starter' => true,
                'sort_order' => $index + 1,
            ]);
        }

        $this->audit($match, 'lineup', 'XI titular actualizado: '.$match->title());

        return back()->with('status', 'XI titular guardado.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function officials(?User $user)
    {
        return User::query()
            ->with('roles')
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['asistente-arbitro', 'asistente-mesa']))
            ->when(
                $user && ! $user->canAccessAllTournaments(),
                fn ($query) => $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0])
            )
            ->orderBy('name')
            ->get();
    }

    public function publish(Request $request): RedirectResponse
    {
        $query = FixtureMatch::query()->accessibleTo($request->user());
        $this->applyFilters($request, $query);
        $count = $query->update([
            'published' => true,
            'published_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Fixture',
            'action' => 'publish',
            'description' => "Fixture publicado en app: {$count} partidos.",
            'auditable_type' => FixtureMatch::class,
            'metadata' => ['count' => $count],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', $count.' partidos publicados para la app.');
    }

    public function publishMatch(FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);
        $match->update([
            'published' => true,
            'published_at' => $match->published_at ?: now(),
        ]);
        $this->audit($match, 'publish', 'Partido publicado en app: '.$match->title());

        return back()->with('status', 'Partido publicado para la app.');
    }

    public function observeMatch(FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);
        $match->update([
            'published' => false,
            'published_at' => null,
            'notes' => trim(($match->notes ? $match->notes."\n" : '').'Resultado observado el '.now()->format('d/m/Y H:i').'.'),
        ]);
        $this->audit($match, 'observe', 'Resultado observado: '.$match->title());

        return back()->with('status', 'Resultado observado. Quedó interno hasta que lo vuelvas a publicar.');
    }

    public function destroy(FixtureMatch $match): RedirectResponse
    {
        $this->assertMatchAccess($match);
        $this->audit($match, 'delete', 'Partido eliminado: '.$match->title());
        $match->delete();

        return redirect()
            ->route('admin.fixture.index')
            ->with('status', 'Partido eliminado del fixture.');
    }

    public function generate(): View
    {
        $user = request()->user();
        $conflicts = FixtureMatch::detectConflicts(
            FixtureMatch::query()
                ->accessibleTo($user)
                ->with(['homeTeam', 'awayTeam', 'field'])
                ->get()
        );

        return view('admin.fixture.generate', [
            'tournaments' => $user->accessibleTournaments(),
            'categories' => Category::query()->accessibleTo($user)->with('tournament')->withCount('teams')->orderBy('birth_year')->get(),
            'fieldsCount' => Field::query()->accessibleTo($user)->where('status', 'available')->count(),
            'conflicts' => $conflicts,
        ]);
    }

    public function generateStore(Request $request): RedirectResponse
    {
        DateTimeInput::merge($request, 'start_at');

        $data = $request->validate([
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'start_at' => ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'gap_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            'priority' => ['required', 'string', 'in:delegation,field,compact'],
            'published' => ['nullable', 'boolean'],
            'scope' => ['nullable', 'string', 'in:group,intergroup'],
            'legs' => ['nullable', 'string', 'in:ida,ida_vuelta'],
            'stage' => ['nullable', 'string', 'max:80'],
        ]);

        abort_unless(
            $request->user()?->canAccessTournament((int) $data['tournament_id']),
            403,
            'Este torneo no está dentro de tu alcance.'
        );

        $category = Category::query()->with('teams')->findOrFail($data['category_id']);
        abort_unless((int) $category->tournament_id === (int) $data['tournament_id'], 422, 'La categoría no pertenece a ese torneo.');
        abort_unless(
            $request->user()?->canAccessTournament((int) $category->tournament_id),
            403,
            'Esta categoría no está dentro de tu alcance.'
        );

        $data['published'] = $request->boolean('published');
        $data['scope'] = $data['scope'] ?? 'group';
        $data['legs'] = $data['legs'] ?? 'ida';
        $result = app(FixtureGenerator::class)->generate($category, $request->user(), $data);
        $created = (int) ($result['created'] ?? 0);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Fixture',
            'action' => 'generate',
            'description' => "Fixture generado para {$category->name}: {$created} partidos.",
            'auditable_type' => Category::class,
            'auditable_id' => $category->id,
            'metadata' => $data + ['created' => $created, 'idle' => $result['idle'] ?? []],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $message = $created > 0
            ? "Se generaron {$created} partidos para {$category->name}."
            : 'No se generaron partidos nuevos. Revisá que haya al menos 2 equipos y que no esté duplicado el cruce.';
        if ($created > 0 && ! empty($result['idle'])) {
            $message .= ' Quedaron sin rival: '.implode(', ', $result['idle']).'.';
        }

        return redirect()
            ->route('admin.fixture.index', ['category_id' => $category->id])
            ->with('status', $message);
    }

    private function form(FixtureMatch $match, string $method, string $url, string $title): View
    {
        $user = auth()->user();

        return view('admin.fixture.form', [
            'match' => $match,
            'method' => $method,
            'url' => $url,
            'title' => $title,
            'tournaments' => $user->accessibleTournaments(),
            'categories' => Category::query()->accessibleTo($user)->with('tournament')->orderBy('birth_year')->get(),
            'teams' => Team::query()->accessibleTo($user)->with(['category', 'delegation'])->orderBy('name')->get(),
            'fields' => Field::query()->accessibleTo($user)->with('venue')->orderBy('name')->get(),
            'officials' => $this->officials($user),
            'knockoutMatches' => $match->exists
                ? FixtureMatch::query()
                    ->accessibleTo($user)
                    ->with(['homeTeam', 'awayTeam'])
                    ->where('category_id', $match->category_id)
                    ->whereKeyNot($match->id)
                    ->orderBy('scheduled_at')
                    ->get()
                : collect(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?FixtureMatch $match = null): array
    {
        DateTimeInput::merge($request, 'scheduled_at');

        $data = $request->validate([
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'field_id' => ['required', 'exists:fields,id'],
            'home_team_id' => ['required', 'exists:teams,id', 'different:away_team_id'],
            'away_team_id' => ['required', 'exists:teams,id', 'different:home_team_id'],
            'scheduled_at' => ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'stage' => ['required', 'string', 'max:80'],
            'round' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'string', 'in:scheduled,live,paused,finished,suspended,rescheduled,validated,reopened'],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:30'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:30'],
            'minute' => ['nullable', 'string', 'max:12'],
            'duration_minutes' => ['required', 'integer', 'min:20', 'max:120'],
            'referee_name' => ['nullable', 'string', 'max:120'],
            'referee_user_id' => ['nullable', 'exists:users,id'],
            'assistant_user_id' => ['nullable', 'exists:users,id'],
            'scorer_user_id' => ['nullable', 'exists:users,id'],
            'period' => ['nullable', 'string', 'in:1t,2t,et,pen'],
            'published' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1200'],
            'next_match_id' => ['nullable', 'exists:matches,id'],
            'next_slot' => ['nullable', 'string', 'in:home,away'],
        ]);

        $category = Category::findOrFail($data['category_id']);
        abort_unless(
            $request->user()?->canAccessTournament((int) $data['tournament_id']),
            403,
            'Este torneo no está dentro de tu alcance.'
        );
        abort_unless((int) $category->tournament_id === (int) $data['tournament_id'], 422);

        $home = Team::findOrFail($data['home_team_id']);
        $away = Team::findOrFail($data['away_team_id']);
        abort_unless((int) $home->category_id === (int) $category->id, 422);
        abort_unless((int) $away->category_id === (int) $category->id, 422);
        abort_unless($request->user()?->canAccessTournament((int) $home->tournament_id), 403);

        $field = Field::query()->with('venue')->findOrFail($data['field_id']);
        abort_unless(
            Field::query()->accessibleTo($request->user())->whereKey($field->id)->exists(),
            403,
            'Esta cancha no está dentro de tu alcance.'
        );
        if ($field->venue?->tournament_id) {
            abort_unless((int) $field->venue->tournament_id === (int) $data['tournament_id'], 422, 'La cancha no pertenece a ese torneo.');
        }

        $data['published'] = $request->boolean('published');
        $data['published_at'] = $data['published'] ? ($match?->published_at ?: now()) : null;
        $data['round'] = $data['round'] ?: $data['stage'];
        $data['next_match_id'] = $data['next_match_id'] ?? null;
        $data['next_slot'] = $data['next_match_id'] ? ($data['next_slot'] ?? 'home') : null;

        if ($data['next_match_id']) {
            abort_if($match && (int) $data['next_match_id'] === (int) $match->id, 422, 'El cruce siguiente no puede ser este mismo partido.');
        }

        if (! empty($data['referee_user_id'])) {
            $data['referee_name'] = User::query()->find($data['referee_user_id'])?->name ?: $data['referee_name'];
        }

        return $data;
    }

    private function applyFilters(Request $request, $query): void
    {
        $user = $request->user();

        $query
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('stage', 'like', "%{$search}%")
                        ->orWhere('round', 'like', "%{$search}%")
                        ->orWhere('referee_name', 'like', "%{$search}%")
                        ->orWhereHas('homeTeam', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('awayTeam', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('category', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('field', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status') && $request->string('status')->toString() !== 'all', fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('tournament_id'), function ($query) use ($request, $user) {
                $tournamentId = $request->integer('tournament_id');
                abort_unless($user?->canAccessTournament($tournamentId), 403, 'Este torneo no está dentro de tu alcance.');
                $query->where('tournament_id', $tournamentId);
            })
            ->when($request->filled('round'), fn ($query) => $query->where('round', $request->string('round')->toString()))
            ->when($request->filled('team_id'), function ($query) use ($request) {
                $teamId = $request->integer('team_id');
                $query->where(fn ($query) => $query->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId));
            })
            ->when($request->filled('date'), fn ($query) => $query->whereDate('scheduled_at', $request->string('date')->toString()))
            ->when($request->filled('field_id'), fn ($query) => $query->where('field_id', $request->integer('field_id')));
    }

    private function assertMatchAccess(FixtureMatch $match): void
    {
        abort_unless(
            auth()->user()?->canAccessTournament((int) $match->tournament_id),
            403,
            'Este partido no está dentro de tu alcance.'
        );
    }

    private function audit(FixtureMatch $match, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Fixture',
            'action' => $action,
            'description' => $description,
            'auditable_type' => FixtureMatch::class,
            'auditable_id' => $match->id,
            'metadata' => [
                'title' => $match->title(),
                'status' => $match->status,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
