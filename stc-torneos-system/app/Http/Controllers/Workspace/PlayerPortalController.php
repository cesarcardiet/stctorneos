<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\User;
use App\Services\PlayerPerformanceService;
use App\Support\QrCode;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlayerPortalController extends Controller
{
    public function home(Request $request, PlayerPerformanceService $performance): View
    {
        $player = $this->player($request);
        $player->ensureDocuments();

        return view('workspace.player-portal.home', $this->layoutData($request, $player, [
            'title' => 'Mi ficha',
            'heading' => 'Mi ficha',
            'subheading' => $player->team?->tournament?->name ?? 'STC Torneos',
            'active' => 'Inicio',
            'stats' => $performance->summary($player),
            'recentEvents' => $performance->recentEvents($player, 5),
        ]));
    }

    public function stats(Request $request, PlayerPerformanceService $performance): View
    {
        $player = $this->player($request);
        $player->load(['team.category', 'team.tournament', 'team.delegation']);

        return view('workspace.player-portal.stats', $this->layoutData($request, $player, [
            'title' => 'Mis estadísticas',
            'heading' => 'Mis estadísticas',
            'subheading' => $player->fullName().' · '.$player->team?->name,
            'active' => 'Estadísticas',
            'stats' => $performance->summary($player),
            'matchHistory' => $performance->matchHistory($player),
            'recentEvents' => $performance->recentEvents($player),
        ]));
    }

    public function ficha(Request $request, PlayerPerformanceService $performance): View
    {
        $player = $this->player($request);
        $player->load(['guardian', 'team.category', 'team.tournament', 'team.delegation', 'documents']);
        $player->ensureDocuments();

        return view('workspace.player-portal.ficha', $this->layoutData($request, $player, [
            'title' => 'Datos de mi ficha',
            'heading' => 'Ficha técnica',
            'subheading' => $player->fullName(),
            'active' => 'Ficha',
            'stats' => $performance->summary($player),
        ]));
    }

    public function documents(Request $request): View
    {
        $player = $this->player($request);
        $player->load(['team.category', 'team.tournament', 'documents']);
        $player->ensureDocuments();

        return view('workspace.player-portal.documents', $this->layoutData($request, $player, [
            'title' => 'Mis documentos',
            'heading' => 'Mis documentos',
            'subheading' => 'Consultá y descargá tu documentación STC.',
            'active' => 'Documentos',
        ]));
    }

    public function export(Request $request): View
    {
        $player = $this->player($request);
        $player->load(['guardian', 'team.category', 'team.tournament', 'team.delegation', 'documents']);
        $player->ensureDocuments();

        return view('workspace.player-portal.export', [
            'player' => $player,
        ]);
    }

    public function credential(Request $request): View
    {
        $player = $this->player($request);
        $player->load(['team.category', 'team.tournament', 'team.delegation']);
        $payload = route('workspace.player.credential');
        $qrUrl = QrCode::url($payload);

        return view('workspace.player-portal.credential', compact('player', 'qrUrl'));
    }

    public function downloadDocument(Request $request, PlayerDocument $document): BinaryFileResponse
    {
        $player = $this->player($request);
        abort_unless((int) $document->player_id === (int) $player->id, 403);
        abort_unless($document->file_path, 404);

        $path = public_path($document->file_path);
        abort_unless(is_file($path), 404, 'El archivo no está disponible.');

        return response()->download(
            $path,
            $document->original_name ?: basename($document->file_path)
        );
    }

    private function player(Request $request): Player
    {
        /** @var User $user */
        $user = $request->user();
        $player = $user->linkedPlayer();
        abort_unless($player instanceof Player, 403);

        $player->loadMissing(['team.category.tournament', 'team.delegation', 'guardian']);

        return $player;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function layoutData(Request $request, Player $player, array $overrides = []): array
    {
        $tournament = $player->team?->tournament;
        \App\Support\WorkspaceContext::rememberTournament($tournament);

        return array_merge([
            'player' => $player,
            'category' => $player->team?->category,
            'tournament' => $tournament,
        ], $overrides);
    }
}
