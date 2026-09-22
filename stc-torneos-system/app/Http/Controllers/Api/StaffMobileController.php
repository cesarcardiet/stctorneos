<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Workspace\Concerns\ResolvesWorkspace;
use App\Models\FixtureMatch;
use App\Models\MatchSheet;
use App\Models\MatchSheetEvent;
use App\Models\Player;
use App\Models\TeamStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffMobileController extends ApiController
{
    use ResolvesWorkspace;

    public function matches(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isMatchStaffOnly() || $user->canAccessAdminWeb(), 403, 'Esta sección es solo para staff de partidos.');

        $scope = $request->string('scope')->toString() ?: 'today';
        $tournamentIds = $user->assignedTournamentIds();

        $query = FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam', 'field', 'category.tournament'])
            ->when($tournamentIds !== [], fn ($builder) => $builder->whereIn('tournament_id', $tournamentIds))
            ->orderBy('scheduled_at');

        $query = match ($scope) {
            'live' => $query->where('status', 'live'),
            'mine' => $query->where('referee_user_id', $user->id),
            'all' => $query->where(fn ($builder) => $builder
                ->where('published', true)
                ->orWhereIn('status', ['live', 'finished', 'validated', 'scheduled'])),
            default => $query->whereDate('scheduled_at', now()->toDateString()),
        };

        $matches = $query->get()->map(fn (FixtureMatch $match) => $this->staffMatchPayload($match, $user));

        return $this->ok([
            'scope' => $scope,
            'matches' => $matches->values(),
        ]);
    }

    public function show(Request $request, FixtureMatch $match): JsonResponse
    {
        $user = $request->user();
        $this->assertStaffMatchAccess($user, $match);

        $match->load([
            'homeTeam.players' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name'),
            'awayTeam.players' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name'),
            'homeTeam.delegation',
            'awayTeam.delegation',
            'field',
            'category.tournament',
            'sheet.events.player',
            'sheet.events.team',
            'sheet.incidents',
        ]);

        $sheet = $this->ensureSheet($match);
        $canOperate = $this->canOperateMatch($user);

        return $this->ok([
            'match' => $this->staffMatchPayload($match, $user),
            'category_id' => $match->category_id,
            'can_operate' => $canOperate,
            'sheet' => [
                'id' => $sheet->id,
                'status' => $sheet->status,
                'status_label' => $sheet->statusLabel(),
                'locked' => (bool) $sheet->locked,
                'home_score' => $sheet->home_score,
                'away_score' => $sheet->away_score,
                'notes' => $sheet->notes,
                'referee_name' => $sheet->referee_name,
                'events' => $sheet->events->map(fn (MatchSheetEvent $event) => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'label' => $event->typeLabel(),
                    'minute' => $event->minute,
                    'team_id' => $event->team_id,
                    'team_name' => $event->team?->name,
                    'player_id' => $event->player_id,
                    'player_name' => $event->player?->fullName(),
                ])->values(),
                'incidents' => $sheet->incidents->map(fn ($incident) => [
                    'id' => $incident->id,
                    'title' => $incident->title,
                    'type' => $incident->type,
                    'notes' => $incident->notes,
                    'status' => $incident->status,
                ])->values(),
            ],
            'players' => [
                'home' => $match->homeTeam?->players->map(fn (Player $player) => $this->playerPayload($player))->values() ?? [],
                'away' => $match->awayTeam?->players->map(fn (Player $player) => $this->playerPayload($player))->values() ?? [],
            ],
            'discipline' => $sheet->events
                ->filter(fn (MatchSheetEvent $event) => in_array($event->type, ['yellow', 'red', 'staff_yellow', 'staff_red'], true))
                ->map(fn (MatchSheetEvent $event) => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'label' => $event->typeLabel(),
                    'minute' => $event->minute,
                    'team_name' => $event->team?->name,
                    'player_name' => $event->player?->fullName(),
                ])->values(),
        ]);
    }

    public function addEvent(Request $request, FixtureMatch $match): JsonResponse
    {
        $user = $request->user();
        $this->assertStaffMatchAccess($user, $match);
        abort_unless($this->canOperateMatch($user), 403, 'No podés operar esta planilla.');

        $sheet = $this->ensureSheet($match);
        abort_if($sheet->locked, 403, 'La planilla ya está cerrada.');

        $data = $request->validate([
            'type' => ['required', 'string', 'in:goal,assist,yellow,red,substitution'],
            'player_id' => ['nullable', 'exists:players,id'],
            'team_id' => ['required', 'exists:teams,id'],
            'minute' => ['nullable', 'integer', 'min:0', 'max:130'],
        ]);

        abort_unless(in_array((int) $data['team_id'], [(int) $match->home_team_id, (int) $match->away_team_id], true), 422);

        if (filled($data['player_id'] ?? null)) {
            $player = Player::query()->findOrFail($data['player_id']);
            abort_unless((int) $player->team_id === (int) $data['team_id'], 422, 'Ese jugador no es de ese equipo.');
        }

        MatchSheetEvent::create([
            'match_sheet_id' => $sheet->id,
            'type' => $data['type'],
            'player_id' => $data['player_id'] ?? null,
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

        return $this->ok(['sheet_id' => $sheet->id], 'Evento cargado en la planilla.');
    }

    public function updateReport(Request $request, FixtureMatch $match): JsonResponse
    {
        $user = $request->user();
        $this->assertStaffMatchAccess($user, $match);
        abort_unless($this->canOperateMatch($user), 403, 'No podés editar el informe.');

        $sheet = $this->ensureSheet($match);
        abort_if($sheet->locked, 403, 'La planilla ya está cerrada.');

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $sheet->update(['notes' => $data['notes'] ?? null]);

        return $this->ok(['notes' => $sheet->notes], 'Informe arbitral actualizado.');
    }

    private function assertStaffMatchAccess($user, FixtureMatch $match): void
    {
        abort_unless($user->isMatchStaffOnly() || $user->canAccessAdminWeb(), 403, 'Esta sección es solo para staff de partidos.');
        abort_unless($user->canAccessTournament((int) $match->tournament_id), 403, 'Este partido no está dentro de tu alcance.');
    }

    private function ensureSheet(FixtureMatch $match): MatchSheet
    {
        $sheet = $match->sheet ?: MatchSheet::create([
            'match_id' => $match->id,
            'status' => 'draft',
            'validation_status' => 'pending',
            'current_step' => 1,
            'referee_name' => $match->referee_name,
        ]);
        $sheet->ensureSignatures();

        return $sheet->fresh(['events.player', 'events.team', 'incidents', 'match']);
    }

    /**
     * @return array<string, mixed>
     */
    private function staffMatchPayload(FixtureMatch $match, $user): array
    {
        $payload = $this->matchPayload($match);
        $payload['category_id'] = $match->category_id;
        $payload['tournament_id'] = $match->tournament_id;
        $payload['tournament_name'] = $match->category?->tournament?->name;
        $payload['referee'] = $match->refereeLabel();
        $payload['assigned_to_me'] = (int) $match->referee_user_id === (int) $user->id;
        $payload['field'] = $match->field?->name;

        return $payload;
    }
}
