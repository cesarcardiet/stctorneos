<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\ResolvesWorkspace;
use App\Models\Category;
use App\Models\CategoryPoll;
use App\Models\CategoryPollVote;
use App\Models\ContentPost;
use App\Models\Delegation;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\MatchSheet;
use App\Models\MatchSheetEvent;
use App\Models\MatchSheetIncident;
use App\Support\FairPlayRules;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use App\Services\FixtureGenerator;
use App\Services\DelegateWelcomeService;
use App\Services\GuardianInvitationService;
use App\Services\RosterShareService;
use App\Support\CategoryWorkspace;
use App\Support\Countries;
use App\Support\ShieldPayload;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActionController extends Controller
{
    use ResolvesWorkspace;

    public function addTeam(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'delegation_id' => ['nullable', 'integer', 'exists:delegations,id'],
            'delegation_name' => ['nullable', 'string', 'max:180'],
            'group_name' => $this->groupNameRules($category),
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
        ]);

        $clubName = $data['delegation_name'] ?? $data['name'];
        $clubId = $this->clubIdForTournament($category->tournament_id, $data['delegation_id'] ?? null);

        $team = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => $data['name'],
            'delegation_id' => $clubId,
            'delegation_name' => $clubName,
            'group_name' => $this->normalizeGroupName($data['group_name'] ?? null),
            'country_code' => $data['country_code'] ?? null,
            'player_capacity' => max(14, (int) $category->max_players),
            'status' => 'approved',
            'roster_open' => true,
        ]);
        $team->attachClub($clubId, $clubName);
        $this->storeTeamShield($request, $team);
        if (! $team->country_code && $team->delegation?->country) {
            $team->update(['country_code' => Countries::guessCode($team->delegation->country)]);
        }

        $this->audit('create', $team, 'Equipo agregado desde Operación: '.$team->name);

        return back()->with('status', $team->name.' quedó en la categoría.');
    }

    public function addClub(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canManageClubs($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
        ]);

        $country = Countries::name($data['country_code'] ?? null) ?: 'Argentina';
        $existed = Delegation::query()
            ->where('tournament_id', $category->tournament_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();

        $club = Delegation::resolveForClub((int) $category->tournament_id, $data['name'], $country);
        $club->update(['country' => $country]);
        $club->syncLinkedRecords();

        $payload = ShieldPayload::fromRequest($request);
        if ($payload) {
            $club->storeLogo($payload[0], $payload[1]);
        } else {
            $club->reuseKnownLogoIfMissing();
        }

        $this->audit('create', $club, 'Club agregado desde Operación: '.$club->name);

        return redirect()
            ->route('workspace.categories.clubs', $category)
            ->with('status', $existed
                ? $club->name.' ya estaba en el torneo. Quedó actualizado y los equipos se vincularon.'
                : $club->name.' quedó como club. Ahora podés sumarle equipos.');
    }

    public function updateClub(Request $request, Category $category, Delegation $delegation): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertClub($category, $delegation);
        $this->assertClubVisible($request->user(), $delegation);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'city' => ['nullable', 'string', 'max:120'],
            'delegate_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'delegate_user_ids' => ['nullable', 'array'],
            'delegate_user_ids.*' => ['integer', 'exists:users,id'],
            'sync_delegates' => ['nullable', 'boolean'],
            'delegate_name' => ['nullable', 'string', 'max:160'],
            'delegate_email' => ['nullable', 'email', 'max:180'],
            'delegate_phone' => ['nullable', 'string', 'max:80'],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
        ]);

        if (! $request->user()->restrictsToAssignedClub()) {
            $this->syncClubDelegates($category->tournament, $delegation, $data);
        }

        $contact = $delegation->fresh()->principalDelegate();
        $delegation->update([
            'name' => $data['name'],
            'country' => Countries::name($data['country_code'] ?? null) ?: ($delegation->country ?: 'Argentina'),
            'city' => $data['city'] ?? null,
            'delegate_name' => ($data['delegate_name'] ?? null) ?: ($contact?->name ?? $delegation->delegate_name),
            'delegate_email' => ($data['delegate_email'] ?? null) ?: ($contact?->email ?? $delegation->delegate_email),
            'delegate_phone' => $data['delegate_phone'] ?? $contact?->phone ?? $delegation->delegate_phone,
        ]);
        $delegation->syncLinkedRecords();

        $payload = ShieldPayload::fromRequest($request);
        if ($payload) {
            $delegation->storeLogo($payload[0], $payload[1]);
        }

        $this->audit('update', $delegation, 'Club actualizado: '.$delegation->name);

        return back()->with('status', $delegation->name.' quedó actualizado. Los equipos siguen en esta delegación.');
    }

    public function updateClubShield(Request $request, Category $category, Delegation $delegation): JsonResponse|RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertClub($category, $delegation);
        $this->assertClubVisible($request->user(), $delegation);
        abort_unless($this->canEdit($request->user()), 403);

        $request->validate([
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
        ]);

        $payload = ShieldPayload::fromRequest($request);
        if (! $payload || ! $delegation->storeLogo($payload[0], $payload[1])) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'No pudimos guardar el escudo. Probá con PNG, JPG o WebP.'], 422);
            }

            return back()->with('status', 'No pudimos guardar el escudo. Probá con PNG, JPG o WebP.');
        }

        $delegation->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => $delegation->logoUrl(),
                'message' => 'Escudo del club actualizado.',
            ]);
        }

        return back()->with('status', 'Escudo del club actualizado.');
    }

    public function assignClubDelegate(Request $request, Category $category, Delegation $delegation): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertClub($category, $delegation);
        $this->assertClubVisible($request->user(), $delegation);

        [$user, $created, $plainPassword] = $this->storeDelegateForClub($request, $category->tournament, $delegation);
        $welcome = app(DelegateWelcomeService::class)->send(
            $user,
            $category->tournament,
            $delegation,
            $created,
            $plainPassword,
        );

        return $this->delegateAssignedResponse($user, $delegation, $welcome, $created);
    }

    public function assignTournamentClubDelegate(Request $request, Tournament $tournament, Delegation $delegation): RedirectResponse
    {
        $this->assertTournament($tournament);
        $this->assertClubOfTournament($tournament, $delegation);
        $this->assertClubVisible($request->user(), $delegation);

        [$user, $created, $plainPassword] = $this->storeDelegateForClub($request, $tournament, $delegation);
        $welcome = app(DelegateWelcomeService::class)->send(
            $user,
            $tournament,
            $delegation,
            $created,
            $plainPassword,
        );

        return $this->delegateAssignedResponse($user, $delegation, $welcome, $created);
    }

    public function destroyClub(Request $request, Category $category, Delegation $delegation): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertClub($category, $delegation);
        $this->assertClubVisible($request->user(), $delegation);
        abort_unless($this->canManageClubs($request->user()), 403);

        $name = $delegation->name;
        $delegation->unlinkTeams();
        Delegation::deleteUnusedPath($delegation->logo_path);
        $this->audit('delete', $delegation, 'Club eliminado desde Operación: '.$name);
        $delegation->delete();

        return redirect()
            ->route('workspace.categories.clubs', $category)
            ->with('status', $name.' se eliminó. Los equipos quedaron, sin esa delegación.');
    }

    public function addTournamentClub(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->assertTournament($tournament);
        abort_unless($this->canManageClubs($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
        ]);

        $country = Countries::name($data['country_code'] ?? null) ?: 'Argentina';
        $existed = Delegation::query()
            ->where('tournament_id', $tournament->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();

        $club = Delegation::resolveForClub((int) $tournament->id, $data['name'], $country);
        $club->update(['country' => $country]);
        $club->syncLinkedRecords();

        $payload = ShieldPayload::fromRequest($request);
        if ($payload) {
            $club->storeLogo($payload[0], $payload[1]);
        } else {
            $club->reuseKnownLogoIfMissing();
        }

        $this->audit('create', $club, 'Club agregado desde Operación: '.$club->name);

        return redirect()
            ->route('workspace.tournaments.clubs', $tournament)
            ->with('status', $existed
                ? $club->name.' ya estaba en el torneo. Quedó actualizado y los equipos se vincularon.'
                : $club->name.' quedó como club. Ahora podés sumarle equipos.');
    }

    public function updateTournamentClub(Request $request, Tournament $tournament, Delegation $delegation): RedirectResponse
    {
        $this->assertTournament($tournament);
        $this->assertClubOfTournament($tournament, $delegation);
        $this->assertClubVisible($request->user(), $delegation);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'city' => ['nullable', 'string', 'max:120'],
            'delegate_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'delegate_user_ids' => ['nullable', 'array'],
            'delegate_user_ids.*' => ['integer', 'exists:users,id'],
            'sync_delegates' => ['nullable', 'boolean'],
            'delegate_name' => ['nullable', 'string', 'max:160'],
            'delegate_email' => ['nullable', 'email', 'max:180'],
            'delegate_phone' => ['nullable', 'string', 'max:80'],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
        ]);

        if (! $request->user()->restrictsToAssignedClub()) {
            $this->syncClubDelegates($tournament, $delegation, $data);
        }

        $contact = $delegation->fresh()->principalDelegate();
        $delegation->update([
            'name' => $data['name'],
            'country' => Countries::name($data['country_code'] ?? null) ?: ($delegation->country ?: 'Argentina'),
            'city' => $data['city'] ?? null,
            'delegate_name' => ($data['delegate_name'] ?? null) ?: ($contact?->name ?? $delegation->delegate_name),
            'delegate_email' => ($data['delegate_email'] ?? null) ?: ($contact?->email ?? $delegation->delegate_email),
            'delegate_phone' => $data['delegate_phone'] ?? $contact?->phone ?? $delegation->delegate_phone,
        ]);
        $delegation->syncLinkedRecords();

        $payload = ShieldPayload::fromRequest($request);
        if ($payload) {
            $delegation->storeLogo($payload[0], $payload[1]);
        }

        $this->audit('update', $delegation, 'Club actualizado: '.$delegation->name);

        return back()->with('status', $delegation->name.' quedó actualizado. Los equipos siguen en esta delegación.');
    }

    public function updateTournamentClubShield(Request $request, Tournament $tournament, Delegation $delegation): JsonResponse|RedirectResponse
    {
        $this->assertTournament($tournament);
        $this->assertClubOfTournament($tournament, $delegation);
        abort_unless($this->canEdit($request->user()), 403);

        $request->validate([
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
        ]);

        $payload = ShieldPayload::fromRequest($request);
        if (! $payload || ! $delegation->storeLogo($payload[0], $payload[1])) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'No pudimos guardar el escudo. Probá con PNG, JPG o WebP.'], 422);
            }

            return back()->with('status', 'No pudimos guardar el escudo. Probá con PNG, JPG o WebP.');
        }

        $delegation->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => $delegation->logoUrl(),
                'message' => 'Escudo del club actualizado.',
            ]);
        }

        return back()->with('status', 'Escudo del club actualizado.');
    }

    public function destroyTournamentClub(Request $request, Tournament $tournament, Delegation $delegation): RedirectResponse
    {
        $this->assertTournament($tournament);
        $this->assertClubOfTournament($tournament, $delegation);
        abort_unless($this->canManageClubs($request->user()), 403);

        $name = $delegation->name;
        $delegation->unlinkTeams();
        Delegation::deleteUnusedPath($delegation->logo_path);
        $this->audit('delete', $delegation, 'Club eliminado desde Operación: '.$name);
        $delegation->delete();

        return redirect()
            ->route('workspace.tournaments.clubs', $tournament)
            ->with('status', $name.' se eliminó. Los equipos quedaron, sin esa delegación.');
    }

    public function addTournamentTeam(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->assertTournament($tournament);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'delegation_id' => ['required', 'integer', 'exists:delegations,id'],
            'name' => ['nullable', 'string', 'max:180'],
        ]);

        $category = Category::query()
            ->where('tournament_id', $tournament->id)
            ->findOrFail($data['category_id']);
        $this->assertCategory($category);

        $club = Delegation::query()->findOrFail($data['delegation_id']);
        $this->assertClubOfTournament($tournament, $club);

        $name = trim((string) ($data['name'] ?? '')) ?: $club->name;

        $exists = Team::query()
            ->where('category_id', $category->id)
            ->where('delegation_id', $club->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return back()->with('status', $name.' ya está en '.$category->name.'. Poné otro nombre si es un segundo equipo de esa categoría.');
        }

        $team = Team::create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'name' => $name,
            'delegation_id' => $club->id,
            'delegation_name' => $club->name,
            'country_code' => $club->countryCode() ?: Countries::guessCode($club->country),
            'player_capacity' => max(14, (int) $category->max_players),
            'status' => 'approved',
            'roster_open' => true,
        ]);
        $team->attachClub($club->id, $club->name);

        $this->audit('create', $team, 'Equipo agregado desde Delegaciones: '.$team->name.' · '.$category->name);

        return back()->with('status', $team->name.' quedó en '.$category->name.'.');
    }

    public function addPlayer(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canManageRoster($request->user()), 403);

        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'name' => ['nullable', 'string', 'max:180'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:40'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_phone' => ['nullable', 'string', 'max:80'],
            'guardian_email' => ['nullable', 'email', 'max:180'],
            'guardian_relationship' => ['nullable', 'string', Rule::in(Guardian::relationshipOptions())],
        ]);

        $team = Team::query()
            ->where('category_id', $category->id)
            ->findOrFail($data['team_id']);
        $this->assertDelegateRosterEditable($request->user(), $team);
        abort_unless(
            Team::query()->accessibleTo($request->user())->whereKey($team->id)->exists(),
            403,
            'Este plantel no está dentro de tu alcance.'
        );

        if (filled($data['first_name'] ?? null) || filled($data['last_name'] ?? null)) {
            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            if ($firstName === '' || $lastName === '') {
                throw ValidationException::withMessages([
                    'last_name' => 'Cargá apellido y nombre del jugador.',
                ]);
            }
        } elseif (filled($data['name'] ?? null)) {
            [$lastName, $firstName] = $this->splitPlayerName($data['name']);
        } else {
            throw ValidationException::withMessages([
                'last_name' => 'Cargá apellido y nombre del jugador.',
            ]);
        }

        if (filled($data['guardian_name'] ?? null)
            && ! filled($data['guardian_phone'] ?? null)
            && ! filled($data['guardian_email'] ?? null)) {
            throw ValidationException::withMessages([
                'guardian_phone' => 'Indicá teléfono o email del tutor.',
            ]);
        }

        $player = Player::create([
            'team_id' => $team->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'document_number' => $data['document_number'] ?? null,
            'nationality' => 'Argentina',
            'status' => filled($data['guardian_name'] ?? null) ? 'awaiting_guardian' : 'pending',
        ]);

        foreach (Player::documentTypes() as $type) {
            $player->documents()->firstOrCreate(['type' => $type], ['status' => 'pending']);
        }

        app(GuardianInvitationService::class)->ensureGuardianFromPlayerForm($player, [
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_phone' => $data['guardian_phone'] ?? null,
            'guardian_email' => $data['guardian_email'] ?? null,
            'guardian_relationship' => $data['guardian_relationship'] ?? null,
        ]);

        $this->audit('create', $player, 'Jugador agregado desde Operación: '.$player->fullName());

        $email = trim((string) ($data['guardian_email'] ?? ''));
        if ($email !== '') {
            try {
                $mailSent = null;
                $invitation = app(GuardianInvitationService::class)->create(
                    $player->fresh('guardian'),
                    $email,
                    $request->user()->id,
                    sendMail: true,
                    mailSent: $mailSent
                );

                return redirect()
                    ->to(route('workspace.categories.players.show', [$category, $player]).'#confirmacion-tutor')
                    ->with('status', $this->tutorInviteStatus($invitation, $player, $mailSent));
            } catch (ValidationException $exception) {
                return redirect()
                    ->to(route('workspace.categories.players.show', [$category, $player]).'#confirmacion-tutor')
                    ->with('status', $player->fullName().' quedó cargado, pero el email del tutor no sirve para el enlace: '.collect($exception->errors())->flatten()->first())
                    ->withErrors($exception->errors());
            } catch (\Throwable $exception) {
                report($exception);
                $invitation = $player->fresh()->latestGuardianInvitation();
                if ($invitation && ($invitation->isUsable() || $invitation->canViewFicha())) {
                    return redirect()
                        ->to(route('workspace.categories.players.show', [$category, $player]).'#confirmacion-tutor')
                        ->with('status', $player->fullName().' quedó cargado. El mail puede fallar si SMTP no está configurado; copiá el link abajo o envialo por WhatsApp.');
                }

                return redirect()
                    ->to(route('workspace.categories.players.show', [$category, $player]).'#confirmacion-tutor')
                    ->with('status', $player->fullName().' quedó cargado. Completá un email de tutor distinto y generá el enlace.');
            }
        }

        return redirect()
            ->to(route('workspace.categories.players.show', [$category, $player]).'#confirmacion-tutor')
            ->with('status', $player->fullName().' quedó cargado. Completá el email del tutor abajo y tocá Generar enlace.');
    }

    public function addMatch(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canScheduleMatches($request->user()), 403);
        $this->combineDateTime($request, 'scheduled_at');

        $data = $request->validate([
            'home_team_id' => ['required', 'exists:teams,id', 'different:away_team_id'],
            'away_team_id' => ['required', 'exists:teams,id'],
            'field_id' => ['required', 'exists:fields,id'],
            'scheduled_at' => ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'stage' => ['required', 'string', 'max:80'],
            'round' => ['nullable', 'string', 'max:80'],
            'return_leg' => ['nullable', 'boolean'],
        ]);

        $home = Team::query()->where('category_id', $category->id)->findOrFail($data['home_team_id']);
        $away = Team::query()->where('category_id', $category->id)->findOrFail($data['away_team_id']);
        $field = Field::query()->accessibleTo($request->user())->findOrFail($data['field_id']);

        $match = FixtureMatch::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'field_id' => $field->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'scheduled_at' => $data['scheduled_at'],
            'stage' => $data['stage'],
            'round' => CategoryWorkspace::normalizeRound($data['round'] ?? null, $data['stage']),
            'status' => 'scheduled',
            'duration_minutes' => max(20, (int) $category->period_duration * max(1, (int) $category->periods)),
            'published' => false,
        ]);

        $this->audit('create', $match, 'Partido cargado desde Operación.');

        if ($request->boolean('return_leg')) {
            $returnRound = CategoryWorkspace::normalizeRound($data['round'] ?? null, $data['stage']);
            if (preg_match('/Fecha\s+(\d+)/', $returnRound, $found)) {
                $returnRound = 'Fecha '.((int) $found[1] + 1);
            } else {
                $returnRound = $returnRound.' (vuelta)';
            }

            FixtureMatch::create([
                'tournament_id' => $category->tournament_id,
                'category_id' => $category->id,
                'field_id' => $field->id,
                'home_team_id' => $away->id,
                'away_team_id' => $home->id,
                'scheduled_at' => Carbon::parse($data['scheduled_at'])->addMinutes(max(80, (int) $match->duration_minutes + 15)),
                'stage' => $data['stage'],
                'round' => $returnRound,
                'status' => 'scheduled',
                'duration_minutes' => $match->duration_minutes,
                'published' => false,
            ]);
        }

        return back()->with('status', $request->boolean('return_leg') ? 'Partido de ida y vuelta agregado.' : 'Partido agregado.');
    }

    public function generateFixture(Request $request, Category $category, FixtureGenerator $generator): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canScheduleMatches($request->user()), 403);
        $this->combineDateTime($request, 'start_at');

        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'gap_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            'priority' => ['required', 'string', 'in:delegation,field,compact'],
            'published' => ['nullable', 'boolean'],
            'scope' => ['nullable', 'string', 'in:group,intergroup'],
            'legs' => ['nullable', 'string', 'in:ida,ida_vuelta'],
            'stage' => ['nullable', 'string', 'max:80'],
            'fill_byes' => ['nullable', 'boolean'],
            'confirm_uneven' => ['nullable', 'boolean'],
            'mix_pairs' => ['nullable', 'array'],
            'mix_pairs.*' => ['nullable', 'string', 'max:4'],
        ]);
        $data['published'] = $request->boolean('published');
        $data['scope'] = $data['scope'] ?? 'group';
        $data['legs'] = $data['legs'] ?? 'ida';
        $data['fill_byes'] = $request->boolean('fill_byes');
        $data['mix_pairs'] = $data['mix_pairs'] ?? [];

        $result = $generator->generate($category, $request->user(), $data);
        $created = (int) ($result['created'] ?? 0);
        $idle = $result['idle'] ?? [];
        $this->audit('generate', $category, "Fixture generado para {$category->name}: {$created} partidos.");

        if ($created <= 0) {
            return back()->with('status', 'No se generaron partidos nuevos. Revisá que haya al menos 2 equipos, canchas disponibles y que no esté duplicado el cruce.');
        }

        $message = "Se generaron {$created} partidos para {$category->name}.";
        if ($idle !== []) {
            $message .= ' Quedaron sin rival: '.implode(', ', $idle).'.';
        }

        return back()->with('status', $message);
    }

    public function publishFixture(Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canScheduleMatches(auth()->user()), 403);

        $count = FixtureMatch::query()
            ->where('category_id', $category->id)
            ->update([
                'published' => true,
                'published_at' => now(),
            ]);

        $this->audit('publish', $category, "Fixture publicado en app: {$count} partidos.");

        return back()->with('status', $count.' partidos publicados para la app.');
    }

    public function publishMatch(Category $category, FixtureMatch $match): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canScheduleMatches(auth()->user()), 403);

        $match->update([
            'published' => true,
            'published_at' => $match->published_at ?: now(),
        ]);
        $this->audit('publish', $match, 'Partido publicado en app: '.$match->title());

        return back()->with('status', 'Partido publicado para la app.');
    }

    public function observeMatch(Category $category, FixtureMatch $match): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canScheduleMatches(auth()->user()), 403);

        $match->update([
            'published' => false,
            'published_at' => null,
            'notes' => trim(($match->notes ? $match->notes."\n" : '').'Resultado observado el '.now()->format('d/m/Y H:i').'.'),
        ]);
        $this->audit('observe', $match, 'Resultado observado: '.$match->title());

        return back()->with('status', 'Resultado observado. Quedó interno hasta que lo vuelvas a publicar.');
    }

    public function destroyMatch(Category $category, FixtureMatch $match): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canDeleteMatches(auth()->user()), 403, 'Solo el administrador puede eliminar partidos.');

        $title = $match->title();
        $this->audit('delete', $match, 'Partido eliminado: '.$title);
        $match->delete();

        $previous = (string) url()->previous();
        $target = str_contains($previous, '/partidos/')
            ? route('workspace.categories.standings', $category)
            : $previous;

        return redirect()
            ->to($target !== '' ? $target : route('workspace.categories.standings', $category))
            ->with('status', 'Partido eliminado: '.$title.'.');
    }

    public function destroyAllMatches(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canScheduleMatches($request->user()), 403);

        $request->validate([
            'confirm' => ['required', 'string', 'in:BORRAR'],
        ]);

        $count = FixtureMatch::query()->where('category_id', $category->id)->count();
        FixtureMatch::query()->where('category_id', $category->id)->delete();
        $this->audit('delete', $category, "Se borraron {$count} partidos de {$category->name}.");

        return back()->with('status', $count > 0
            ? "Se borraron {$count} partidos de {$category->name}."
            : 'Esta categoría no tenía partidos para borrar.');
    }

    public function updatePlayer(Request $request, Category $category, Player $player): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPlayer($category, $player);
        $player->loadMissing('team.category');
        $user = $request->user();

        abort_unless(
            $this->canEditPlayer($user, $player),
            403,
            $this->rosterLockReasonFor($user, $player->team) ?? 'No podés editar este jugador.'
        );

        if (! $this->isMatchStaffOnly($user)) {
            $this->assertDelegateRosterEditable($user, $player->team);
            abort_unless($this->canManageRoster($user), 403);
            abort_unless(
                Team::query()->accessibleTo($user)->whereKey($player->team_id)->exists(),
                403,
                'Este plantel no está dentro de tu alcance.'
            );
            abort_unless($this->canEditFullPlayer($user), 403);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date', 'after_or_equal:1990-01-01', 'before_or_equal:today'],
            'nationality' => ['nullable', 'string', Rule::in(array_values(Countries::all()))],
            'address' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', Rule::in(Player::positions())],
            'jersey_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'kit_size' => ['nullable', 'string', Rule::in(Player::kitSizes())],
            'preferred_foot' => ['nullable', 'string', Rule::in(Player::preferredFeet())],
            'height' => ['nullable', 'string', 'max:40'],
            'weight' => ['nullable', 'string', 'max:40'],
            'blood_type' => ['nullable', 'string', 'max:20'],
            'medical_coverage' => ['nullable', 'string', 'max:120'],
            'allergies' => ['nullable', 'string', 'max:180'],
            'medication' => ['nullable', 'string', 'max:180'],
            'illnesses' => ['nullable', 'string', 'max:180'],
            'restrictions' => ['nullable', 'string', 'max:180'],
            'emergency_contact' => ['nullable', 'string', 'max:180'],
            'medical_notes' => ['nullable', 'string', 'max:1200'],
            'vaccination_calendar_complete' => ['nullable', 'in:0,1,'],
            'ongoing_treatment' => ['nullable', 'in:0,1,'],
            'ongoing_treatment_notes' => ['nullable', 'string', 'max:1200'],
            'observation_reason' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Player::statusLabels()))],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_document_number' => ['nullable', 'string', 'max:40'],
            'guardian_relationship' => ['nullable', 'string', Rule::in(Guardian::relationshipOptions())],
            'guardian_phone' => ['nullable', 'string', 'max:80'],
            'guardian_email' => ['nullable', 'email', 'max:180'],
            'guardian_alternate_contact' => ['nullable', 'string', 'max:255'],
            'consent_status' => ['nullable', 'string', 'in:pending,approved,rejected'],
        ]);

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'document_number' => $data['document_number'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'jersey_number' => $data['jersey_number'] ?? null,
            'status' => $data['status'],
        ];

        if ($request->exists('nationality')) {
            $payload['nationality'] = $data['nationality'] ?? $player->nationality;
        }
        if ($request->exists('kit_size')) {
            $payload['kit_size'] = $data['kit_size'] ?? null;
        }

        foreach ([
            'address',
            'position',
            'preferred_foot',
            'height',
            'weight',
            'blood_type',
            'medical_coverage',
            'allergies',
            'medication',
            'illnesses',
            'restrictions',
            'emergency_contact',
            'medical_notes',
            'observation_reason',
        ] as $field) {
            if ($request->exists($field)) {
                $payload[$field] = $data[$field] ?? null;
            }
        }

        if ($request->exists('vaccination_calendar_complete')) {
            $value = $request->input('vaccination_calendar_complete');
            $payload['vaccination_calendar_complete'] = $value === '' || $value === null
                ? null
                : $request->boolean('vaccination_calendar_complete');
        }

        if ($request->exists('ongoing_treatment')) {
            $value = $request->input('ongoing_treatment');
            $payload['ongoing_treatment'] = $value === '' || $value === null
                ? null
                : $request->boolean('ongoing_treatment');
            $payload['ongoing_treatment_notes'] = $payload['ongoing_treatment'] === true
                ? ($data['ongoing_treatment_notes'] ?? null)
                : null;
        } elseif ($request->exists('ongoing_treatment_notes')) {
            $payload['ongoing_treatment_notes'] = $data['ongoing_treatment_notes'] ?? null;
        }

        $player->update($payload);

        app(GuardianInvitationService::class)->ensureGuardianFromPlayerForm($player, $data);

        $this->audit('update', $player, 'Ficha actualizada: '.$player->fullName());

        return back()->with('status', 'Ficha guardada.');
    }

    public function inviteGuardian(Request $request, Category $category, Player $player): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPlayer($category, $player);
        $player->loadMissing('team');
        $this->assertDelegateRosterEditable($request->user(), $player->team);
        abort_unless($this->canManageRoster($request->user()), 403);
        abort_unless(
            Team::query()->accessibleTo($request->user())->whereKey($player->team_id)->exists(),
            403,
            'Este plantel no está dentro de tu alcance.'
        );

        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
        ]);

        $player->load('guardian');
        app(GuardianInvitationService::class)->ensureGuardianFromPlayerForm($player, [
            'guardian_email' => $data['email'],
            'guardian_name' => $player->guardian?->name,
            'guardian_relationship' => $player->guardian?->relationship,
            'guardian_phone' => $player->guardian?->phone,
            'guardian_document_number' => $player->guardian?->document_number,
            'consent_status' => $player->guardian?->consent_status ?? 'pending',
        ]);

        $mailSent = null;
        $invitation = app(GuardianInvitationService::class)->create(
            $player->fresh('guardian'),
            $data['email'],
            $request->user()->id,
            sendMail: true,
            mailSent: $mailSent
        );

        $this->audit('invite', $player, 'Invitación al tutor generada desde Operación: '.$invitation->email);

        return redirect()
            ->to(route('workspace.categories.players.show', [$category, $player]).'#confirmacion-tutor')
            ->with('status', $this->tutorInviteStatus($invitation, $player, $mailSent));
    }

    public function regenerateGuardianInvite(Request $request, Category $category, Player $player): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPlayer($category, $player);
        $player->loadMissing('team');
        $this->assertDelegateRosterEditable($request->user(), $player->team);
        abort_unless($this->canManageRoster($request->user()), 403);

        $player->load('guardian');
        $email = $player->latestGuardianInvitation()?->email ?: $player->guardian?->email;
        abort_unless($email, 422, 'Cargá un correo del tutor para regenerar el enlace.');

        $mailSent = null;
        $invitation = app(GuardianInvitationService::class)->create(
            $player,
            $email,
            $request->user()->id,
            sendMail: true,
            mailSent: $mailSent
        );

        $this->audit('invite', $player, 'Invitación al tutor regenerada desde Operación: '.$invitation->email);

        return redirect()
            ->to(route('workspace.categories.players.show', [$category, $player]).'#confirmacion-tutor')
            ->with('status', $this->tutorInviteStatus($invitation, $player, $mailSent));
    }

    public function invalidateGuardianInvite(Request $request, Category $category, Player $player): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPlayer($category, $player);
        $player->loadMissing('team');
        $this->assertDelegateRosterEditable($request->user(), $player->team);
        abort_unless($this->canManageRoster($request->user()), 403);

        app(GuardianInvitationService::class)->invalidate($player);
        $this->audit('invite_invalidate', $player, 'Invitación al tutor invalidada desde Operación.');

        return back()->with('status', 'El enlace del tutor quedó invalidado.');
    }

    public function destroyPlayer(Request $request, Category $category, Player $player): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPlayer($category, $player);
        $player->loadMissing('team');
        $this->assertDelegateRosterEditable($request->user(), $player->team);
        abort_unless($this->canManageRoster($request->user()), 403);
        abort_unless(
            Team::query()->accessibleTo($request->user())->whereKey($player->team_id)->exists(),
            403,
            'Este plantel no está dentro de tu alcance.'
        );

        $name = $player->fullName();
        $showUrl = route('workspace.categories.players.show', [$category, $player]);
        $teamUrl = $player->team_id
            ? route('workspace.categories.teams.show', [$category, $player->team_id])
            : route('workspace.categories.players', $category);

        $this->audit('delete', $player, 'Jugador eliminado del plantel: '.$name);
        $player->delete();

        return $this->redirectAfterDelete($showUrl, $teamUrl, $name.' se sacó del plantel.');
    }

    public function uploadPlayerDocument(Request $request, Category $category, Player $player): RedirectResponse|JsonResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPlayer($category, $player);
        $player->loadMissing('team');
        $this->assertDelegateRosterEditable($request->user(), $player->team);
        abort_unless($this->canEditFullPlayer($request->user()), 403);
        abort_unless(
            Team::query()->accessibleTo($request->user())->whereKey($player->team_id)->exists(),
            403,
            'Este plantel no está dentro de tu alcance.'
        );

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(Player::uploadDocumentTypes())],
            'file' => ['required', 'image', 'max:8192'],
        ]);

        $player->ensureDocuments();
        $document = $player->documents()->firstOrCreate(
            ['type' => $data['type']],
            ['status' => 'pending']
        );
        $document->storeImage($request->file('file'), $request->user()->name);
        $document->refresh();

        if ($data['type'] === 'Foto del jugador') {
            $player->update(['photo_path' => $document->file_path]);
            $player->unsetRelation('documents');
            $player->load('documents');
        }

        $this->audit('update', $player, 'Imagen cargada en ficha: '.$data['type']);

        $message = $data['type'] === 'Foto del jugador'
            ? 'La foto del jugador quedó actualizada.'
            : $data['type'].' quedó cargada. Tocá Ver para abrirla.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => $player->fresh(['documents'])->photoUrl(),
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    public function reviewDocument(Request $request, Category $category, PlayerDocument $document): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $document->load('player.team');
        abort_unless((int) $document->player?->team?->category_id === (int) $category->id, 404);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,observed,approved,rejected'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        abort_if(
            ! PlayerDocument::requiresClubReviewForType((string) $document->type) && $data['status'] === 'approved',
            422,
            'Este documento no requiere aprobación manual del club.'
        );

        $document->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $document->notes,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Documento '.$document->statusLabel().'.');
    }

    public function reviewInscription(Request $request, Category $category, Player $player): RedirectResponse|JsonResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPlayer($category, $player);
        abort_unless($this->canReviewInscriptions($request->user()), 403);

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['decision'] === 'approve') {
            $player->update([
                'status' => 'approved',
                'observation_reason' => null,
                'notes' => filled($data['notes'] ?? null) ? $data['notes'] : $player->notes,
            ]);
            $message = $player->fullName().' quedó aprobado.';
            $action = 'approve';
        } else {
            $reason = trim((string) ($data['notes'] ?? '')) ?: 'Inscripción rechazada.';
            $player->update([
                'status' => 'rejected',
                'observation_reason' => $reason,
                'notes' => $reason,
            ]);
            $message = $player->fullName().' quedó rechazado.';
            $action = 'reject';
        }

        $this->audit('update', $player, 'Inscripción '.$action.': '.$player->fullName());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $player->status,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('workspace.categories.inscriptions', $category)
            ->with('status', $message);
    }

    public function updateTeam(Request $request, Category $category, Team $team): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertTeam($category, $team);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'group_name' => $this->groupNameRules($category, $team->group_name),
            'delegation_id' => ['nullable', 'integer', 'exists:delegations,id'],
            'delegation_name' => ['nullable', 'string', 'max:180'],
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'status' => ['required', 'string', 'in:approved,pending,observed,blocked'],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
        ]);

        $clubId = $this->clubIdForTournament($category->tournament_id, $data['delegation_id'] ?? null);

        $team->update([
            'name' => $data['name'],
            'group_name' => $this->normalizeGroupName($data['group_name'] ?? null),
            'country_code' => $data['country_code'] ?? null,
            'status' => $data['status'],
        ]);
        $team->attachClub($clubId, $data['delegation_name'] ?? $data['name']);
        $this->storeTeamShield($request, $team);
        $this->audit('update', $team, 'Equipo actualizado: '.$team->name);

        return back()->with('status', 'Equipo actualizado.');
    }

    public function destroyTeam(Request $request, Category $category, Team $team): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertTeam($category, $team);
        abort_unless($this->canEdit($request->user()), 403);

        $name = $team->name;
        $showUrl = route('workspace.categories.teams.show', [$category, $team]);
        $listUrl = route('workspace.categories.teams', $category);
        $this->audit('delete', $team, 'Equipo eliminado desde Operación: '.$name);
        $team->delete();

        return $this->redirectAfterDelete($showUrl, $listUrl, $name.' se eliminó de la categoría, con sus jugadores y partidos.');
    }

    public function updateTeamShield(Request $request, Category $category, Team $team): JsonResponse|RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertTeam($category, $team);
        abort_unless($this->canEdit($request->user()), 403);

        $request->validate([
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
        ]);

        if (! $this->storeTeamShield($request, $team)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'No pudimos guardar el escudo. Probá con PNG, JPG o WebP.'], 422);
            }

            return back()->with('status', 'No pudimos guardar el escudo. Probá con PNG, JPG o WebP.');
        }

        $team->refresh()->load('delegation');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => $team->shieldUrl(),
                'message' => 'Escudo del club actualizado.',
            ]);
        }

        return back()->with('status', 'Escudo actualizado.');
    }

    public function addStaff(Request $request, Category $category, Team $team): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertTeam($category, $team);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'role' => ['required', 'string', 'in:'.implode(',', array_keys(TeamStaff::roleLabels()))],
        ]);

        $team->staffMembers()->create($data + ['status' => 'active']);

        return back()->with('status', 'Cuerpo técnico actualizado.');
    }

    public function generateRosterLink(Request $request, Category $category, Team $team, RosterShareService $rosterShare): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertTeam($category, $team);
        $this->assertDelegateRosterEditable($request->user(), $team);

        $invitation = $rosterShare->create($team, $request->user());
        $this->audit('roster_link', $team, 'Enlace de plantel generado para '.$team->name.'.');

        return back()->with('status', 'Enlace listo. Compartilo con quien cargue el plantel: '.$invitation->publicUrl());
    }

    public function regenerateRosterLink(Request $request, Category $category, Team $team, RosterShareService $rosterShare): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertTeam($category, $team);
        $this->assertDelegateRosterEditable($request->user(), $team);

        $invitation = $rosterShare->regenerate($team, $request->user());
        $this->audit('roster_link', $team, 'Enlace de plantel regenerado para '.$team->name.'.');

        return back()->with('status', 'Nuevo enlace generado. El anterior ya no sirve.');
    }

    public function invalidateRosterLink(Request $request, Category $category, Team $team, RosterShareService $rosterShare): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertTeam($category, $team);
        abort_unless($this->canManageTeamRoster($request->user(), $team), 403);

        $invitation = $rosterShare->activeForTeam($team);
        if ($invitation) {
            $rosterShare->invalidate($invitation);
            $this->audit('roster_link', $team, 'Enlace de plantel invalidado para '.$team->name.'.');
        }

        return back()->with('status', 'El enlace de plantel quedó invalidado.');
    }

    public function moveTeamGroup(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'group_name' => $this->groupNameRules($category),
        ]);

        $team = Team::query()->where('category_id', $category->id)->findOrFail($data['team_id']);
        $group = $this->normalizeGroupName($data['group_name'] ?? null);
        $team->update(['group_name' => $group]);
        $this->audit('update', $team, 'Equipo pasado al grupo '.($group ?: 'sin grupo').': '.$team->name);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'team_id' => $team->id, 'group_name' => $group]);
        }

        return back()->with('status', $team->name.' quedó en el grupo '.($group ?: 'sin grupo').'.');
    }

    public function reorderTeams(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'group_name' => ['nullable', 'string', 'max:40'],
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['required', 'integer', 'exists:teams,id'],
        ]);

        $groupKey = CategoryWorkspace::teamOrderStorageKey($this->normalizeGroupName($data['group_name'] ?? null));
        $teamIds = collect($data['team_ids'])->map(fn ($id) => (int) $id)->unique()->values();

        $teams = Team::query()
            ->where('category_id', $category->id)
            ->whereIn('id', $teamIds)
            ->get()
            ->keyBy('id');

        abort_if($teams->count() !== $teamIds->count(), 422, 'Hay equipos que no pertenecen a esta categoría.');

        foreach ($teams as $team) {
            $expected = CategoryWorkspace::teamOrderStorageKey($team->group_name);
            abort_if($expected !== $groupKey, 422, 'Los equipos deben pertenecer al mismo grupo.');
        }

        $config = $category->workspace();
        CategoryWorkspace::saveTeamOrder($config, $groupKey, $teamIds->all());
        $category->workspace_config = $config;
        $category->save();

        $this->audit('update', $category, 'Orden manual de equipos actualizado ('.$groupKey.').');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'group_key' => $groupKey, 'team_ids' => $teamIds->all()]);
        }

        return back()->with('status', 'Orden de equipos guardado.');
    }

    public function reorderMatches(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'phase' => ['nullable', 'string', 'max:80'],
            'round' => ['nullable', 'string', 'max:80'],
            'match_ids' => ['required', 'array', 'min:1'],
            'match_ids.*' => ['required', 'integer', Rule::exists('matches', 'id')],
        ]);

        $phase = trim((string) ($data['phase'] ?? 'all')) ?: 'all';
        $round = trim((string) ($data['round'] ?? 'all')) ?: 'all';
        $scope = CategoryWorkspace::matchOrderScope($phase === 'all' ? null : $phase, $round === 'all' ? null : $round);
        $matchIds = collect($data['match_ids'])->map(fn ($id) => (int) $id)->unique()->values();

        $matches = FixtureMatch::query()
            ->where('category_id', $category->id)
            ->whereIn('id', $matchIds)
            ->get();

        abort_if($matches->count() !== $matchIds->count(), 422, 'Hay partidos que no pertenecen a esta categoría.');

        $config = $category->workspace();
        CategoryWorkspace::saveMatchOrder($config, $scope, $matchIds->all());
        $category->workspace_config = $config;
        $category->save();

        $this->audit('update', $category, 'Orden manual de partidos actualizado ('.$scope.').');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'scope' => $scope, 'match_ids' => $matchIds->all()]);
        }

        return back()->with('status', 'Orden de partidos guardado.');
    }

    public function saveMatchResult(Request $request, Category $category, FixtureMatch $match): RedirectResponse|JsonResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canOperateMatch($request->user()), 403);

        $data = $request->validate([
            'home_score' => ['required', 'integer', 'min:0', 'max:30'],
            'away_score' => ['required', 'integer', 'min:0', 'max:30'],
            'status' => ['required', 'string', 'in:scheduled,live,finished,validated,suspended,rescheduled'],
        ]);

        $match->update([
            'home_score' => $data['home_score'],
            'away_score' => $data['away_score'],
            'status' => $data['status'] === 'validated' ? 'finished' : $data['status'],
            'published' => $match->published || in_array($data['status'], ['finished', 'validated'], true),
        ]);
        $match->loadMissing('sheet');
        if ($match->sheet) {
            $match->sheet->update([
                'home_score' => $data['home_score'],
                'away_score' => $data['away_score'],
            ]);
        }

        if (in_array($match->status, ['live', 'scheduled', 'suspended', 'rescheduled'], true)) {
            $this->unlockSheet($match);
        }

        $this->audit('update', $match, 'Resultado cargado desde Operación: '.$match->statusLabel());

        $message = 'Estado guardado: '.$match->statusLabel().'.';
        if ($request->expectsJson()) {
            return $this->matchSavedResponse($request, $match, $message);
        }

        return redirect()
            ->route('workspace.categories.standings', $category)
            ->with('status', $message);
    }

    public function updateMatchSchedule(Request $request, Category $category, FixtureMatch $match): RedirectResponse|JsonResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canScheduleMatches($request->user()), 403);
        $this->combineDateTime($request, 'scheduled_at');

        $data = $request->validate([
            'field_id' => ['required', 'exists:fields,id'],
            'scheduled_at' => ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
        ]);

        $field = Field::query()->with('venue')->findOrFail($data['field_id']);
        $sameTournament = (int) ($field->venue?->tournament_id ?? $category->tournament_id) === (int) $category->tournament_id;
        abort_unless(
            $sameTournament || $request->user()?->canAccessAllTournaments(),
            403,
            'Esa cancha no es de este torneo.'
        );

        $match->update([
            'field_id' => $field->id,
            'scheduled_at' => $data['scheduled_at'],
        ]);
        $this->audit('update', $match, 'Horario y cancha actualizados: '.$match->title().' · '.$field->name.' · '.Carbon::parse($data['scheduled_at'])->format('d/m H:i'));

        return $this->matchSavedResponse($request, $match, 'Horario y cancha guardados.');
    }

    public function addSheetEvent(Request $request, Category $category, FixtureMatch $match): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canOperateMatch($request->user()), 403);

        $sheet = $this->sheetFor($match);
        abort_if($sheet->locked, 403, 'La planilla ya está cerrada.');

        $data = $request->validate([
            'type' => ['required', 'string', 'in:goal,assist,yellow,red,substitution'],
            'actor_kind' => ['nullable', 'string', 'in:player,staff'],
            'player_id' => ['nullable', 'exists:players,id'],
            'team_staff_id' => ['nullable', 'exists:team_staff,id'],
            'team_id' => ['required', 'exists:teams,id'],
            'minute' => ['nullable', 'integer', 'min:0', 'max:130'],
        ]);

        abort_unless(in_array((int) $data['team_id'], [(int) $match->home_team_id, (int) $match->away_team_id], true), 422);

        $actorKind = $data['actor_kind'] ?? 'player';
        if (in_array($data['type'], ['goal', 'assist', 'substitution'], true)) {
            $actorKind = 'player';
        }

        $eventType = $data['type'];
        $playerId = null;
        $staffId = null;

        if ($actorKind === 'staff' && in_array($data['type'], ['yellow', 'red'], true)) {
            abort_unless(filled($data['team_staff_id'] ?? null), 422, 'Elegí un integrante del cuerpo técnico.');

            $staff = TeamStaff::query()->findOrFail($data['team_staff_id']);
            abort_unless((int) $staff->team_id === (int) $data['team_id'], 422, 'Ese integrante no es de ese equipo.');
            abort_unless($staff->isActive(), 422, 'Ese integrante no está activo en el cuerpo técnico.');

            $eventType = $data['type'] === 'yellow' ? 'staff_yellow' : 'staff_red';
            $staffId = $staff->id;
        } else {
            if (filled($data['player_id'] ?? null)) {
                $player = Player::query()->findOrFail($data['player_id']);
                abort_unless((int) $player->team_id === (int) $data['team_id'], 422, 'Ese jugador no es de ese equipo.');
                $playerId = $player->id;
            }
        }

        MatchSheetEvent::create([
            'match_sheet_id' => $sheet->id,
            'type' => $eventType,
            'player_id' => $playerId,
            'team_staff_id' => $staffId,
            'team_id' => $data['team_id'],
            'minute' => $data['minute'] ?? null,
        ]);

        $sheet->load(['events', 'match']);
        $sheet->recalcScore();
        $sheet->update(['status' => 'loading']);
        $match->update([
            'home_score' => $sheet->home_score,
            'away_score' => $sheet->away_score,
        ]);

        return back()->with('status', 'Evento cargado en la planilla.');
    }

    public function addSheetIncident(Request $request, Category $category, FixtureMatch $match): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canOperateMatch($request->user()), 403);

        $sheet = $this->sheetFor($match);
        abort_if($sheet->locked, 403, 'La planilla ya está cerrada.');

        $data = $request->validate([
            'fair_play_kind' => ['required', 'string', Rule::in(array_keys(FairPlayRules::kindLabels()))],
            'team_id' => ['required', 'exists:teams,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'moment' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        abort_unless(in_array((int) $data['team_id'], [(int) $match->home_team_id, (int) $match->away_team_id], true), 422);

        $team = Team::query()->findOrFail($data['team_id']);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = FairPlayRules::kindLabels()[$data['fair_play_kind']] ?? 'Incidencia Fair Play';
        }

        $notes = collect([
            filled($data['moment'] ?? null) ? 'Momento: '.$data['moment'] : null,
            filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ])->filter()->implode(' · ');

        $sheet->update(['incident_team_id' => $team->id]);

        $sheet->incidents()->create([
            'type' => 'Fair Play',
            'title' => $title,
            'related_name' => $team->name,
            'status' => 'applied',
            'fair_play_kind' => $data['fair_play_kind'],
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $this->audit('update', $sheet, 'Penalización Fair Play cargada: '.$title.' · '.$team->name);

        return back()->with('status', 'Penalización Fair Play registrada. Se sumará al ranking de disciplina al cerrar la planilla.');
    }

    public function destroySheetIncident(Category $category, FixtureMatch $match, MatchSheetIncident $incident): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canOperateMatch(auth()->user()), 403);

        $sheet = $this->sheetFor($match);
        abort_if($sheet->locked, 403, 'La planilla ya está cerrada.');
        abort_unless((int) $incident->match_sheet_id === (int) $sheet->id, 404);

        $title = $incident->title;
        $incident->delete();

        $this->audit('delete', $sheet, 'Penalización Fair Play eliminada: '.$title);

        return back()->with('status', 'Penalización eliminada de la planilla.');
    }

    public function closeSheet(Category $category, FixtureMatch $match): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canOperateMatch(auth()->user()), 403);

        $sheet = $this->sheetFor($match);
        $sheet->load(['events', 'match']);
        $sheet->recalcScore();
        $sheet->update([
            'status' => 'closed',
            'published' => true,
            'published_at' => now(),
            'validation_status' => 'validated',
            'locked' => true,
        ]);
        $match->update([
            'home_score' => $sheet->home_score,
            'away_score' => $sheet->away_score,
            'status' => 'finished',
            'published' => true,
        ]);

        return back()->with('status', 'Planilla cerrada y resultado publicado.');
    }

    public function reopenMatch(Category $category, FixtureMatch $match): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertMatch($category, $match);
        abort_unless($this->canOperateMatch(auth()->user()), 403);

        $this->unlockSheet($match);
        $match->update(['status' => 'live']);
        $this->audit('update', $match, 'Partido reabierto para corrección: '.$match->title());

        return back()->with('status', 'Partido reabierto. Quedó en juego para que lo edites y lo vuelvas a cerrar.');
    }

    public function addField(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $venue = Venue::query()->firstOrCreate(
            ['tournament_id' => $category->tournament_id, 'name' => 'Sede principal'],
            ['city' => $category->tournament?->city ?: 'Santa Teresita', 'status' => 'active']
        );

        Field::create([
            'venue_id' => $venue->id,
            'name' => $data['name'],
            'surface' => 'Césped sintético',
            'status' => 'available',
        ]);

        return back()->with('status', 'Cancha agregada.');
    }

    public function addPerson(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($request->user()?->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'delegation_id' => ['nullable', 'integer', 'exists:delegations,id'],
        ]);

        $role = Role::query()->findOrFail($data['role_id']);
        abort_if($role->slug === 'super-admin', 422, 'Ese rol no se asigna desde acá.');

        $clubId = $this->clubIdForTournament($category->tournament_id, $data['delegation_id'] ?? null);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => 'stcdemo',
            'status' => 'active',
            'tournament_id' => $category->tournament_id,
            'delegation_id' => $clubId,
            'current_scope' => $category->tournament?->name,
        ]);

        $user->roles()->sync([
            $role->id => [
                'scope_type' => 'tournament',
                'scope_id' => $category->tournament_id,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
            ],
        ]);

        Invitation::create([
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $role->id,
            'status' => 'pending',
            'token' => Str::random(40),
            'scope_type' => 'tournament',
            'scope_id' => $category->tournament_id,
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit('create', $user, 'Persona agregada a la categoría: '.$user->name);

        if ($clubId) {
            $club = Delegation::query()->find($clubId);
            if ($club && $role->slug === 'delegado') {
                $sameAsClub = strcasecmp(trim((string) $club->delegate_name), trim((string) $club->name)) === 0;
                $club->update([
                    'delegate_name' => (! filled($club->delegate_name) || $sameAsClub) ? $user->name : $club->delegate_name,
                    'delegate_email' => $club->delegate_email ?: $user->email,
                ]);
            }
        }

        return back()->with('status', $user->name.' ya puede entrar con la clave de demo.');
    }

    private function syncClubDelegates(Tournament $tournament, Delegation $club, array $data): void
    {
        if (! array_key_exists('sync_delegates', $data) && ! array_key_exists('delegate_user_ids', $data) && ! array_key_exists('delegate_user_id', $data)) {
            return;
        }

        $ids = collect($data['delegate_user_ids'] ?? []);
        if ($ids->isEmpty() && filled($data['delegate_user_id'] ?? null)) {
            $ids = collect([$data['delegate_user_id']]);
        }

        $users = [];
        foreach ($ids->map(fn ($id) => (int) $id)->filter()->unique() as $id) {
            $user = $this->delegateForTournament($tournament, $id);
            if ($user) {
                $users[] = $user;
            }
        }

        $club->syncDelegates($users);
    }

    private function delegateAssignedResponse(User $user, Delegation $delegation, array $welcome, bool $created): RedirectResponse
    {
        $whatsappUrl = $welcome['whatsapp_url'] ?? null;
        $status = $user->name.' quedó como delegado de '.$delegation->name.'.';

        if ($whatsappUrl) {
            $status .= $created
                ? ' Abrí WhatsApp para enviarle el acceso (correo, clave del sistema y link de ingreso).'
                : ' Abrí WhatsApp para reenviarle el acceso a su panel.';
        } else {
            $status .= ' Le enviamos el acceso por correo a '.$user->email.'. Cargá un teléfono válido la próxima vez para mandarlo por WhatsApp.';
        }

        return back()
            ->with('status', $status)
            ->with('delegate_welcome_whatsapp', $whatsappUrl)
            ->with('delegate_welcome_open_whatsapp', (bool) $whatsappUrl);
    }

    private function storeDelegateForClub(Request $request, Tournament $tournament, Delegation $club): array
    {
        abort_unless($this->canAssignDelegates($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['required', 'string', 'max:80'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $phone = trim($data['phone']);
        $role = Role::query()->where('slug', 'delegado')->firstOrFail();
        $user = User::query()->with('roles')->whereRaw('LOWER(email) = ?', [$email])->first();
        $created = false;
        $plainPassword = null;

        if ($user) {
            $blocked = $user->isSuperAdmin()
                || $user->hasRole('admin-torneo')
                || (! $user->hasRole('delegado') && $user->roles->isNotEmpty());

            if ($blocked) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'Ese correo ya está usado por otra persona que no es delegado.',
                ]);
            }
        } else {
            $plainPassword = Str::password(12);
            $user = User::create([
                'name' => $data['name'],
                'email' => $email,
                'phone' => $phone,
                'password' => $plainPassword,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $created = true;
        }

        $user->fill([
            'name' => $data['name'],
            'phone' => $phone,
        ]);
        $user->save();

        if (! $user->hasRole('delegado')) {
            $user->roles()->syncWithoutDetaching([
                $role->id => [
                    'scope_type' => 'tournament',
                    'scope_id' => $tournament->id,
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                ],
            ]);
            $user->unsetRelation('roles');
        }

        $club->assignDelegate($user->fresh(), $phone);
        $this->audit('update', $club, 'Delegado asignado a '.$club->name.': '.$user->name);

        if ($created) {
            Invitation::create([
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $role->id,
                'status' => 'pending',
                'token' => Str::random(40),
                'scope_type' => 'delegation',
                'scope_id' => $club->id,
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(7),
            ]);
        }

        return [$user->fresh(), $created, $plainPassword];
    }

    private function storeTeamShield(Request $request, Team $team): bool
    {
        $payload = ShieldPayload::fromRequest($request);
        if (! $payload) {
            return false;
        }

        return $team->storeClubShield($payload[0], $payload[1]);
    }

    private function tutorInviteStatus(Invitation $invitation, Player $player, ?bool $mailSent = null): string
    {
        if ($mailSent === true) {
            $message = 'Enlace generado y enviado a '.$invitation->email.'. También podés copiarlo abajo o mandarlo por WhatsApp.';
        } elseif ($mailSent === false) {
            $message = 'Enlace generado, pero el correo no se pudo enviar a '.$invitation->email.'. Copialo abajo o mandalo por WhatsApp.';
        } else {
            $message = 'Enlace de confirmación generado. Copialo abajo o envialo por WhatsApp.';
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])->first();

        if ($user?->isTutorAccount()) {
            return $message.' Acceso al panel: correo del tutor y clave inicial '.\App\Support\TutorCredentials::defaultPassword().' (cambiable en Mi cuenta).';
        }

        return $message;
    }

    private function delegateForTournament(Tournament $tournament, mixed $userId): ?User
    {
        $id = (int) $userId;
        if ($id < 1) {
            return null;
        }

        $user = User::query()->with('roles')->find($id);
        abort_unless($user, 422, 'Ese delegado no existe.');
        abort_unless($user->hasRole('delegado'), 422, 'Esa persona tiene que tener rol Delegado.');
        abort_unless($user->canAccessTournament((int) $tournament->id), 403, 'Ese delegado no es de este torneo.');

        return $user;
    }

    private function assertClubOfTournament(Tournament $tournament, Delegation $delegation): Delegation
    {
        abort_unless((int) $delegation->tournament_id === (int) $tournament->id, 404);

        return $delegation;
    }

    private function clubIdForTournament(int $tournamentId, mixed $delegationId): ?int
    {
        $id = (int) $delegationId;
        if ($id < 1) {
            return null;
        }

        $club = Delegation::query()
            ->accessibleTo(auth()->user())
            ->where('tournament_id', $tournamentId)
            ->find($id);

        return $club?->id;
    }

    private function assertClub(Category $category, Delegation $delegation): Delegation
    {
        abort_unless((int) $delegation->tournament_id === (int) $category->tournament_id, 404);

        return $delegation;
    }

    private function assertPlayer(Category $category, Player $player): Player
    {
        $player->loadMissing('team');
        abort_unless((int) $player->team?->category_id === (int) $category->id, 404);

        return $player;
    }

    private function assertTeam(Category $category, Team $team): Team
    {
        abort_unless((int) $team->category_id === (int) $category->id, 404);

        return $team;
    }

    private function redirectAfterDelete(string $removedUrl, string $fallbackUrl, string $status): RedirectResponse
    {
        $previous = url()->previous($fallbackUrl);
        $previousPath = rtrim((string) (parse_url($previous, PHP_URL_PATH) ?: $previous), '/');
        $removedPath = rtrim((string) (parse_url($removedUrl, PHP_URL_PATH) ?: $removedUrl), '/');

        if ($previousPath === $removedPath || str_starts_with($previousPath, $removedPath.'/')) {
            return redirect($fallbackUrl)->with('status', $status);
        }

        return redirect()->to($previous)->with('status', $status);
    }

    private function assertMatch(Category $category, FixtureMatch $match): FixtureMatch
    {
        abort_unless((int) $match->category_id === (int) $category->id, 404);

        return $match;
    }

    private function matchSavedResponse(Request $request, FixtureMatch $match, string $message): RedirectResponse|JsonResponse
    {
        $match->load('field');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $match->statusLabel(),
                'tone' => $match->statusTone(),
                'live' => $match->isLive(),
                'field' => $match->field?->name,
                'when' => $match->scheduled_at?->format('d/m H:i'),
                'home_score' => (int) ($match->home_score ?? 0),
                'away_score' => (int) ($match->away_score ?? 0),
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    private function unlockSheet(FixtureMatch $match): void
    {
        $sheet = $match->sheet;
        if (! $sheet) {
            return;
        }

        $sheet->update([
            'locked' => false,
            'status' => 'loading',
            'validation_status' => 'pending',
        ]);
    }

    private function sheetFor(FixtureMatch $match): MatchSheet
    {
        $sheet = $match->sheet ?: MatchSheet::create([
            'match_id' => $match->id,
            'status' => 'draft',
            'validation_status' => 'pending',
            'current_step' => 1,
            'referee_name' => $match->referee_name,
        ]);
        $sheet->ensureSignatures();

        return $sheet->fresh(['events.player', 'events.teamStaff', 'events.team', 'match.homeTeam', 'match.awayTeam']);
    }

    public function addMedia(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'kind' => ['required', 'string', 'in:news,link,youtube,gallery'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:4000'],
        ]);

        $post = ContentPost::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'author_id' => $request->user()->id,
            'type' => $data['kind'] === 'news' ? 'news' : 'announcement',
            'title' => $data['title'],
            'slug' => Str::slug($data['title']).'-'.Str::random(6),
            'summary' => Str::limit((string) ($data['body'] ?? ''), 160),
            'body' => $data['body'],
            'audience' => 'public',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->audit('create', $post, 'Media agregada desde Operación: '.$post->title);

        return back()->with('status', 'Contenido publicado en la categoría.');
    }

    public function storePoll(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $this->validatedPollPayload($request);

        $poll = CategoryPoll::create([
            'category_id' => $category->id,
            'created_by' => $request->user()->id,
            'question' => $data['question'],
            'is_visible' => $data['is_visible'],
            'show_results' => $data['show_results'],
            'voting_open' => $data['voting_open'],
            'allow_multiple' => $data['allow_multiple'],
            'sort_order' => (int) CategoryPoll::query()->where('category_id', $category->id)->max('sort_order') + 1,
        ]);
        $poll->syncOptions($data['options']);

        $this->audit('create', $poll, 'Encuesta creada: '.$poll->question);

        return redirect()
            ->route('workspace.categories.rankings', $category)
            ->with('status', 'Encuesta publicada en Rankings.');
    }

    public function updatePoll(Request $request, Category $category, CategoryPoll $categoryPoll): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPoll($category, $categoryPoll);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $this->validatedPollPayload($request, $categoryPoll);

        $categoryPoll->update([
            'question' => $data['question'],
            'is_visible' => $data['is_visible'],
            'show_results' => $data['show_results'],
            'voting_open' => $data['voting_open'],
            'allow_multiple' => $data['allow_multiple'],
        ]);

        if ($categoryPoll->votes()->exists()) {
            abort_if(count($data['options']) !== $categoryPoll->options()->count(), 422, 'No podés cambiar las opciones si ya hay votos.');
        } else {
            $categoryPoll->syncOptions($data['options']);
        }

        $this->audit('update', $categoryPoll, 'Encuesta actualizada: '.$categoryPoll->question);

        return redirect()
            ->route('workspace.categories.rankings', $category)
            ->with('status', 'Encuesta actualizada.');
    }

    public function destroyPoll(Request $request, Category $category, CategoryPoll $categoryPoll): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPoll($category, $categoryPoll);
        abort_unless($this->canEdit($request->user()), 403);

        $question = $categoryPoll->question;
        $categoryPoll->delete();

        $this->audit('delete', $category, 'Encuesta eliminada: '.$question);

        return redirect()
            ->route('workspace.categories.rankings', $category)
            ->with('status', 'Encuesta eliminada.');
    }

    public function votePoll(Request $request, Category $category, CategoryPoll $categoryPoll): RedirectResponse
    {
        $category = $this->assertCategory($category);
        $this->assertPoll($category, $categoryPoll);
        abort_unless($categoryPoll->is_visible, 404);
        abort_unless($categoryPoll->voting_open, 422, 'La votación está cerrada.');

        $voterKey = CategoryPoll::voterKey($request);
        abort_if($categoryPoll->hasVoted($voterKey), 422, 'Ya registraste tu voto en esta encuesta.');

        $optionIds = collect($request->input('option_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        abort_if($optionIds->isEmpty(), 422, 'Elegí al menos una opción.');

        $validIds = $categoryPoll->options()->whereIn('id', $optionIds)->pluck('id');
        abort_if($validIds->count() !== $optionIds->count(), 422, 'Opción inválida.');

        if (! $categoryPoll->allow_multiple) {
            abort_if($optionIds->count() !== 1, 422, 'Esta encuesta permite una sola opción.');
        }

        foreach ($validIds as $optionId) {
            CategoryPollVote::create([
                'category_poll_id' => $categoryPoll->id,
                'category_poll_option_id' => $optionId,
                'user_id' => $request->user()?->id,
                'voter_key' => $voterKey,
            ]);
        }

        $this->audit('vote', $categoryPoll, 'Voto registrado en encuesta: '.$categoryPoll->question);

        return redirect()
            ->route('workspace.categories.rankings', $category)
            ->with('status', 'Tu voto quedó registrado.');
    }

    /**
     * @return array{
     *     question: string,
     *     options: list<string>,
     *     is_visible: bool,
     *     show_results: bool,
     *     voting_open: bool,
     *     allow_multiple: bool
     * }
     */
    private function validatedPollPayload(Request $request, ?CategoryPoll $poll = null): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'options' => ['required', 'string', 'max:4000'],
            'is_visible' => ['sometimes', 'boolean'],
            'show_results' => ['sometimes', 'boolean'],
            'voting_open' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
        ]);

        $options = preg_split('/\r\n|\r|\n/', (string) $data['options']) ?: [];
        $options = array_values(array_filter(array_map('trim', $options)));

        abort_if(count($options) < 2, 422, 'Cargá al menos dos opciones (una por línea).');

        return [
            'question' => trim($data['question']),
            'options' => $options,
            'is_visible' => $request->boolean('is_visible'),
            'show_results' => $request->boolean('show_results'),
            'voting_open' => $request->boolean('voting_open'),
            'allow_multiple' => $request->boolean('allow_multiple'),
        ];
    }

    private function assertPoll(Category $category, CategoryPoll $poll): void
    {
        abort_unless((int) $poll->category_id === (int) $category->id, 404);
    }

    public function reorderGroups(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $data = $request->validate([
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['required', 'string', 'max:40'],
        ]);

        $names = collect($data['groups'])->map(fn ($name) => trim((string) $name))->filter()->unique()->values()->all();
        $config = $category->workspace();
        $config['group_order'] = $names;
        $category->workspace_config = $config;
        $category->save();
        $this->audit('update', $category, 'Orden de grupos actualizado: '.implode(', ', $names));

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'groups' => $names]);
        }

        return back()->with('status', 'Orden de grupos actualizado.');
    }

    public function updateSettings(Request $request, Category $category): RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $section = $request->string('section')->toString();

        match ($section) {
            'basic' => $this->saveBasic($request, $category),
            'profile' => $this->saveProfile($request, $category),
            'rules' => $this->saveRules($request, $category),
            'prizes' => $this->savePrizes($request, $category),
            'sport' => $this->saveSport($request, $category),
            'groups' => $this->saveGroups($request, $category),
            'phases' => $this->savePhases($request, $category),
            'rounds' => $this->saveRounds($request, $category),
            'columns' => $this->saveColumns($request, $category),
            'criteria' => $this->saveCriteria($request, $category),
            'result' => $this->saveResult($request, $category),
            'registrations' => $this->saveRegistrations($request, $category),
            'official' => $this->saveOfficial($request, $category),
            default => abort(422, 'Sección no válida.'),
        };

        $this->audit('update', $category, 'Configuración de categoría actualizada: '.$section);

        if ($section === 'groups') {
            return redirect()
                ->route('workspace.categories.standings', $category)
                ->with('status', 'Grupos actualizados. Ya se ven en la categoría.');
        }

        return back()->with('status', 'Cambios guardados.');
    }

    public function updateCategoryBanner(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canEdit($request->user()), 403);

        $request->validate([
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
            'image_file' => ['nullable', 'file', 'max:8192'],
            'image_path' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $category->applyBannerFromRequest($request)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'No pudimos guardar la imagen. Probá con PNG, JPG o un logo del torneo.'], 422);
            }

            return back()->with('status', 'No pudimos guardar la imagen. Probá con PNG, JPG o un logo del torneo.');
        }

        $category->refresh();
        $this->audit('update', $category, 'Imagen de categoría actualizada: '.$category->name);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => $category->bannerUrl(),
                'kind' => 'category',
                'identity' => $category->identityKey(),
                'message' => 'Imagen de la categoría actualizada.',
            ]);
        }

        return back()->with('status', 'Imagen de la categoría actualizada.');
    }

    public function updateTournamentBanner(Request $request, Tournament $tournament): JsonResponse|RedirectResponse
    {
        $this->assertTournament($tournament);
        abort_unless($this->canEdit($request->user()), 403);

        $request->validate([
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
            'logo_file' => ['nullable', 'file', 'max:8192'],
        ]);

        if (! $tournament->applyLogoFromRequest($request)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'No pudimos guardar la imagen del torneo. Probá con PNG, JPG o WebP.'], 422);
            }

            return back()->with('status', 'No pudimos guardar la imagen del torneo. Probá con PNG, JPG o WebP.');
        }

        $tournament->refresh();
        $this->audit('update', $tournament, 'Imagen de torneo actualizada: '.$tournament->name);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => $tournament->logoUrl(),
                'kind' => 'tournament',
                'message' => 'Imagen del torneo actualizada.',
            ]);
        }

        return back()->with('status', 'Imagen del torneo actualizada.');
    }

    private function saveBasic(Request $request, Category $category): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'shield_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
            'image_path' => ['nullable', 'string', 'max:500'],
        ]);

        $category->update(['name' => $data['name']]);
        $category->mergeWorkspace([
            'description' => $data['description'] ?? '',
            'accent_color' => $data['accent_color'] ?: '#00d5ff',
        ]);
        $category->applyBannerFromRequest($request);
    }

    private function saveProfile(Request $request, Category $category): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'modality' => ['required', 'string', Rule::in(Category::modalities())],
            'team_limit' => ['nullable', 'integer', 'min:0', 'max:128'],
            'max_players' => ['nullable', 'integer', 'min:1', 'max:99'],
            'competition_format' => ['nullable', 'string', 'max:80'],
            'periods' => ['nullable', 'integer', 'min:1', 'max:4'],
            'period_duration' => ['nullable', 'integer', 'min:1', 'max:90'],
            'substitutes' => ['nullable', 'integer', 'min:0', 'max:99'],
            'status' => ['required', 'string', Rule::in(array_keys(Category::statusLabels()))],
            'draw_rule' => ['nullable', 'string', 'max:80'],
            'fair_play_on' => ['nullable', 'boolean'],
            'publish' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('publish')) {
            $data['status'] = 'active';
        }

        $category->update([
            'name' => $data['name'],
            'modality' => $data['modality'],
            'team_limit' => $data['team_limit'] ?? $category->team_limit,
            'max_players' => $data['max_players'] ?? $category->max_players,
            'competition_format' => $data['competition_format'] ?: $category->competition_format,
            'periods' => $data['periods'] ?? $category->periods,
            'period_duration' => $data['period_duration'] ?? $category->period_duration,
            'substitutes' => $data['substitutes'] ?? $category->substitutes,
            'status' => $data['status'],
        ]);

        $category->mergeWorkspace([
            'draw_rule' => trim((string) ($data['draw_rule'] ?? '')) ?: ($category->workspace()['draw_rule'] ?? 'Penales'),
            'fair_play_on' => $request->boolean('fair_play_on'),
        ]);
    }

    private function saveRules(Request $request, Category $category): void
    {
        $data = $request->validate([
            'rules' => ['nullable', 'string', 'max:8000'],
        ]);

        $category->update(['rules' => $data['rules'] ?? '']);
    }

    private function savePrizes(Request $request, Category $category): void
    {
        $data = $request->validate([
            'first' => ['nullable', 'string', 'max:180'],
            'second' => ['nullable', 'string', 'max:180'],
            'third' => ['nullable', 'string', 'max:180'],
            'other' => ['nullable', 'string', 'max:180'],
        ]);

        $category->mergeWorkspace([
            'prizes' => [
                'first' => $data['first'] ?? '',
                'second' => $data['second'] ?? '',
                'third' => $data['third'] ?? '',
                'other' => $data['other'] ?? '',
            ],
        ]);
    }

    private function saveSport(Request $request, Category $category): void
    {
        $data = $request->validate([
            'points_win' => ['required', 'integer', 'min:0', 'max:10'],
            'points_draw' => ['required', 'integer', 'min:0', 'max:10'],
            'points_loss' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $category->update($data);
    }

    private function saveGroups(Request $request, Category $category): void
    {
        $data = $request->validate([
            'groups_count' => ['required', 'integer', 'min:1', 'max:'.Category::MAX_GROUPS],
            'assign' => ['nullable', 'string', 'in:keep,random'],
            'group_names' => ['nullable', 'array'],
            'group_names.*' => ['nullable', 'string', 'max:40'],
        ]);

        $count = (int) $data['groups_count'];
        $allowed = Category::groupAlphabet($count);
        $category->update(['groups_count' => $count]);

        $names = [];
        foreach ($allowed as $letter) {
            $custom = trim((string) ($data['group_names'][$letter] ?? ''));
            if ($custom !== '' && strcasecmp($custom, 'Grupo '.$letter) !== 0) {
                $names[$letter] = $custom;
            }
        }
        $config = $category->workspace();
        $config['group_names'] = $names;
        $category->workspace_config = $config;
        $category->save();

        $teams = $category->teams()->orderBy('name')->get()->values();
        if (($data['assign'] ?? 'keep') === 'random') {
            $teams = $teams->shuffle()->values();
            foreach ($teams as $index => $team) {
                $team->update(['group_name' => $allowed[$index % $count]]);
            }

            return;
        }

        foreach ($teams as $index => $team) {
            $current = strtoupper(trim((string) $team->group_name));
            if ($current === '' || ! in_array($current, $allowed, true)) {
                $team->update(['group_name' => $allowed[$index % $count]]);
            }
        }
    }

    private function savePhases(Request $request, Category $category): void
    {
        $data = $request->validate([
            'phases' => ['required', 'array', 'min:1'],
            'phases.*.name' => ['nullable', 'string', 'max:80'],
            'phases.*.mode' => ['nullable', 'string', 'max:80'],
            'phases.*.old' => ['nullable', 'string', 'max:80'],
        ]);

        $phases = collect($data['phases'])
            ->filter(fn (array $phase) => filled($phase['name'] ?? ''))
            ->map(fn (array $phase) => [
                'name' => trim((string) $phase['name']),
                'mode' => $phase['mode'] ?: 'Todos contra Todos',
                'old' => trim((string) ($phase['old'] ?? '')),
            ])
            ->values();

        abort_if($phases->isEmpty(), 422, 'Cargá al menos una fase.');

        foreach ($phases as $phase) {
            if ($phase['old'] !== '' && $phase['old'] !== $phase['name']) {
                FixtureMatch::query()
                    ->where('category_id', $category->id)
                    ->where('stage', $phase['old'])
                    ->update(['stage' => $phase['name']]);
            }
        }

        $saved = $phases->map(fn (array $phase) => [
            'name' => $phase['name'],
            'mode' => $phase['mode'],
        ])->all();

        $category->mergeWorkspace(['phases' => $saved]);
        $category->update([
            'phases' => collect($saved)->pluck('name')->implode(', '),
        ]);
    }

    private function saveRounds(Request $request, Category $category): void
    {
        $data = $request->validate([
            'rounds' => ['required', 'array', 'min:1'],
            'rounds.*.name' => ['nullable', 'string', 'max:80'],
            'rounds.*.old' => ['nullable', 'string', 'max:80'],
        ]);

        $rounds = collect($data['rounds'])
            ->map(fn (array $round) => [
                'name' => trim((string) ($round['name'] ?? '')),
                'old' => trim((string) ($round['old'] ?? '')),
            ])
            ->filter(fn (array $round) => $round['name'] !== '')
            ->unique('name')
            ->values();

        abort_if($rounds->isEmpty(), 422, 'Cargá al menos una fecha.');

        foreach ($rounds as $round) {
            if ($round['old'] !== '' && $round['old'] !== $round['name']) {
                FixtureMatch::query()
                    ->where('category_id', $category->id)
                    ->where('round', $round['old'])
                    ->update(['round' => $round['name']]);
            }
        }

        $names = $rounds->pluck('name')->all();
        $config = $category->workspace();
        $config['rounds'] = $names;
        $category->workspace_config = $config;
        $category->save();
    }

    private function saveColumns(Request $request, Category $category): void
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $allowed = array_keys(\App\Support\CategoryWorkspace::columnLabels());
        $columns = collect($data['columns'] ?? [])
            ->filter(fn ($column) => in_array($column, $allowed, true))
            ->values()
            ->all();

        $category->mergeWorkspace(['visible_columns' => $columns ?: ['pts', 'j', 'g', 'e', 'p']]);
    }

    private function saveCriteria(Request $request, Category $category): void
    {
        $data = $request->validate([
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*' => ['string', 'max:80'],
        ]);

        $criteria = collect($data['criteria'])->map(fn ($item) => trim($item))->filter()->values()->all();
        $category->mergeWorkspace(['criteria' => $criteria]);
        $category->update(['tiebreakers' => $criteria, 'classification_criteria' => implode("\n", $criteria)]);
    }

    private function saveResult(Request $request, Category $category): void
    {
        $data = $request->validate([
            'highlight_first' => ['required', 'integer', 'min:0', 'max:20'],
            'highlight_last' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $category->mergeWorkspace($data);
    }

    private function saveRegistrations(Request $request, Category $category): void
    {
        $data = $request->validate([
            'registrations_open' => ['nullable', 'boolean'],
            'registration_info' => ['nullable', 'string', 'max:4000'],
        ]);

        \App\Services\RegistrationControl::setCategoryOpen(
            $category,
            $request->boolean('registrations_open'),
            $data['registration_info'] ?? null,
        );
    }

    private function saveOfficial(Request $request, Category $category): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'role' => ['nullable', 'string', 'max:120'],
        ]);

        $officials = $category->workspace()['officials'] ?? [];
        $officials[] = ['name' => $data['name'], 'role' => $data['role'] ?: 'Árbitro'];
        $category->mergeWorkspace(['officials' => $officials]);
    }

    /**
     * @return list<mixed>
     */
    private function groupNameRules(Category $category, ?string $current = null): array
    {
        return ['nullable', 'string', Rule::in($category->groupOptions($current))];
    }

    private function normalizeGroupName(?string $group): ?string
    {
        $group = strtoupper(trim(str_ireplace('grupo ', '', (string) $group)));

        return $group !== '' ? $group : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPlayerName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) <= 1) {
            return [$name, $name];
        }

        return [$parts[0], implode(' ', array_slice($parts, 1))];
    }
}
