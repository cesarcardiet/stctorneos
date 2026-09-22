<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Services\PlayerPerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountPortalController extends ApiController
{
    public function tutorPlayers(Request $request): JsonResponse
    {
        abort_unless($request->user()->isTutorAccount(), 403, 'Esta sección es solo para tutores.');

        $players = $request->user()
            ->tutorPlayers()
            ->map(fn (Player $player) => $this->tutorPlayerPayload($player));

        return $this->ok($players->values());
    }

    public function tutorPlayer(Request $request, Player $player): JsonResponse
    {
        abort_unless($request->user()->isTutorAccount(), 403, 'Esta sección es solo para tutores.');
        $player = $this->assertTutorPlayer($request->user(), $player);

        return $this->ok($this->tutorPlayerPayload($player->load(['team.category.tournament', 'team.delegation', 'guardian', 'documents'])));
    }

    public function playerPortal(Request $request, PlayerPerformanceService $performance): JsonResponse
    {
        abort_unless($request->user()->isPlayerAccount(), 403, 'Esta sección es solo para jugadores.');

        $player = $request->user()->linkedPlayer();
        abort_unless($player, 404, 'No encontramos tu ficha de jugador.');

        $player->load(['team.category.tournament', 'team.delegation', 'guardian', 'documents']);
        $player->ensureDocuments();

        return $this->ok([
            'player' => $this->playerPortalPayload($player),
            'stats' => $performance->summary($player),
            'recent_events' => $performance->recentEvents($player, 5)->map(fn ($event) => [
                'minute' => $event->minute,
                'type' => $event->type,
                'label' => $event->typeLabel(),
                'match' => $event->sheet?->match?->title(),
            ])->values(),
            'documents' => $player->documents->map(fn ($document) => [
                'id' => $document->id,
                'type' => $document->type,
                'status' => $document->status,
                'status_label' => $document->statusLabel(),
                'file_url' => $document->fileUrl(),
            ])->values(),
        ]);
    }

    public function workspace(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->restrictsToAssignedClub() || $user->isMatchStaffOnly() || $user->canAccessAdminWeb(),
            403,
            'No tenés acceso al workspace.'
        );

        return $this->ok(WorkspaceMobileController::workspacePayload($user, $this));
    }

    private function assertTutorPlayer($user, Player $player): Player
    {
        $owned = $user->tutorPlayers()->contains(fn (Player $linked) => $linked->id === $player->id);
        abort_unless($owned, 403, 'Este jugador no está vinculado a tu cuenta de tutor.');

        return $player;
    }

    /**
     * @return array<string, mixed>
     */
    private function tutorPlayerPayload(Player $player): array
    {
        $player->loadMissing(['team.category.tournament', 'team.delegation', 'guardian', 'documents']);

        $locked = $player->tutorFichaLocked();

        return [
            'id' => $player->id,
            'name' => $player->fullName(),
            'photo' => $player->photoUrl(),
            'category' => $player->team?->category?->name,
            'club' => $player->team?->delegation?->name,
            'tournament' => $player->team?->category?->tournament?->name,
            'relationship' => $player->guardian?->relationship,
            'status' => $player->status,
            'status_label' => $player->statusLabel(),
            'ficha_locked' => $locked,
            'action_label' => $locked ? 'Ver ficha enviada' : 'Completar ficha',
            'progress' => [
                'profile' => $player->layerOneLabel(),
                'guardian' => $player->layerTwoLabel(),
                'documents' => $player->authorizationSummary(),
                'review' => $player->reviewStatusLabel(),
            ],
            'documents' => $player->documents
                ->map(fn ($document) => [
                    'type' => $document->type,
                    'status' => $document->status,
                    'status_label' => $document->statusLabel(),
                    'file_url' => $document->fileUrl(),
                ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function playerPortalPayload(Player $player): array
    {
        return [
            'id' => $player->id,
            'name' => $player->fullName(),
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'photo' => $player->photoUrl(),
            'position' => $player->position,
            'jersey' => $player->jersey_number,
            'nationality' => $player->nationality,
            'age' => $player->age(),
            'preferred_foot' => $player->preferred_foot,
            'height' => $player->height,
            'notes' => $player->notes,
            'club' => $player->team?->delegation?->name,
            'category' => $player->team?->category?->name,
            'tournament' => $player->team?->tournament?->name,
            'status_label' => $player->statusLabel(),
        ];
    }
}
