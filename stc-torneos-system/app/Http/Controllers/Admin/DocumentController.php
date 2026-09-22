<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Delegation;
use App\Models\DocumentRequirement;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\PlayerDocumentReviewService;
use App\Support\AdminContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.players.index', array_filter([
            'status' => $request->string('status')->toString() ?: 'review',
            'tournament_id' => $request->string('tournament_id')->toString() ?: AdminContext::resolveTournamentId($request),
            'search' => $request->string('search')->toString(),
            'documentation' => $request->string('documentation')->toString(),
        ]));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = PlayerDocument::query()
            ->accessibleTo($request->user())
            ->whereIn('type', Player::documentTypes())
            ->with(['player.team.category', 'player.team.delegation']);
        $this->applyFilters($request, $query);
        $documents = $query->latest()->get();

        return response()->streamDownload(function () use ($documents) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Jugador', 'Delegación', 'Equipo', 'Categoría', 'Documento', 'Estado', 'Habilitación', 'Observación']);

            foreach ($documents as $document) {
                fputcsv($handle, [
                    $document->player?->fullName(),
                    $document->player?->team?->delegation?->name ?? $document->player?->team?->delegation_name,
                    $document->player?->team?->name,
                    $document->player?->team?->category?->name,
                    $document->type,
                    $document->statusLabel(),
                    $document->player?->eligibilityLabel(),
                    $document->notes,
                ]);
            }

            fclose($handle);
        }, 'bandeja-documental.csv');
    }

    public function show(PlayerDocument $document): RedirectResponse
    {
        $document->load('player');
        $this->assertDocumentAccess($document);
        $player = $document->player;
        abort_unless($player, 404);

        return redirect()->route('admin.players.show', [$player, 'tab' => 'aprobacion']);
    }

    public function update(Request $request, PlayerDocument $document, PlayerDocumentReviewService $reviews): RedirectResponse
    {
        $this->assertDocumentAccess($document);
        $reviews->update($document, $reviews->validatedReviewPayload($request), $request->user());

        $player = $document->player;

        return redirect()
            ->route($player ? 'admin.players.show' : 'admin.players.index', $player ? [$player, 'tab' => 'aprobacion'] : ['status' => 'review'])
            ->with('status', 'Revisión documental guardada.');
    }

    public function updateStatus(Request $request, PlayerDocument $document, PlayerDocumentReviewService $reviews): RedirectResponse
    {
        $this->assertDocumentAccess($document);
        $data = $request->validate(['status' => ['required', 'string', 'in:pending,observed,rejected,approved']]);
        $reviews->quickStatus($document, $data['status'], $document->notes, $request->user());

        return back()->with('status', 'Estado documental actualizado.');
    }

    public function enablePlayer(Request $request, PlayerDocument $document, PlayerDocumentReviewService $reviews): RedirectResponse
    {
        $this->assertDocumentAccess($document);
        $player = $document->player;
        abort_unless($player, 404, 'Este documento no tiene jugador asociado.');

        $reviews->enablePlayer($player, $request->user(), 'Documentación');

        return back()->with('status', $player->fullName().' quedó habilitado para jugar.');
    }

    public function requirements(Request $request): View
    {
        $user = $request->user();
        $requirements = DocumentRequirement::query()
            ->accessibleTo($user)
            ->with(['tournament', 'category'])
            ->latest()
            ->get();

        return view('admin.documents.requirements', [
            'requirements' => $requirements,
            'tournaments' => $user->accessibleTournaments(),
            'categories' => Category::query()->accessibleTo($user)->orderBy('name')->get(),
            'types' => DocumentRequirement::defaultTypes(),
            'subheading' => 'Definí qué documentos pide cada torneo o categoría, si son obligatorios y si vencen.',
        ]);
    }

    public function storeRequirement(Request $request): RedirectResponse
    {
        $data = $this->validatedRequirement($request);
        $requirement = DocumentRequirement::create($data);
        $this->auditRequirement($requirement, 'create', 'Requisito documental creado: '.$requirement->type);

        return back()->with('status', 'Requisito documental guardado.');
    }

    public function updateRequirement(Request $request, DocumentRequirement $requirement): RedirectResponse
    {
        $this->assertRequirementAccess($requirement);
        $requirement->update($this->validatedRequirement($request));
        $this->auditRequirement($requirement, 'update', 'Requisito documental actualizado: '.$requirement->type);

        return back()->with('status', 'Requisito documental actualizado.');
    }

    public function destroyRequirement(DocumentRequirement $requirement): RedirectResponse
    {
        $this->assertRequirementAccess($requirement);
        $this->auditRequirement($requirement, 'delete', 'Requisito documental eliminado: '.$requirement->type);
        $requirement->delete();

        return back()->with('status', 'Requisito documental eliminado.');
    }

    public function applyRequirements(Request $request): RedirectResponse
    {
        $user = $request->user();
        $requirements = DocumentRequirement::query()->accessibleTo($user)->get();
        $created = 0;

        $players = Player::query()
            ->with(['team', 'documents'])
            ->when(
                ! $user->canAccessAllTournaments(),
                fn ($query) => $query->whereHas('team', fn ($team) => $team->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]))
            )
            ->get();

        foreach ($players as $player) {
            foreach ($requirements as $requirement) {
                if ($requirement->tournament_id && (int) $player->team?->tournament_id !== (int) $requirement->tournament_id) {
                    continue;
                }
                if ($requirement->category_id && (int) $player->team?->category_id !== (int) $requirement->category_id) {
                    continue;
                }

                $document = $player->documents->firstWhere('type', $requirement->type);
                if ($document) {
                    continue;
                }

                PlayerDocument::create([
                    'player_id' => $player->id,
                    'type' => $requirement->type,
                    'status' => 'pending',
                    'expires_at' => $requirement->has_expiration && $requirement->validity_days
                        ? now()->addDays($requirement->validity_days)->toDateString()
                        : null,
                ]);
                $created++;
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Documentación',
            'action' => 'apply_requirements',
            'description' => "Requisitos documentales aplicados: {$created} pendientes creados.",
            'auditable_type' => DocumentRequirement::class,
            'metadata' => ['created' => $created],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', $created.' documentos pendientes creados según los requisitos.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRequirement(Request $request): array
    {
        $data = $request->validate([
            'tournament_id' => ['nullable', 'exists:tournaments,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'type' => ['required', 'string', 'max:120'],
            'required' => ['nullable', 'boolean'],
            'has_expiration' => ['nullable', 'boolean'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:1460'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        if (! empty($data['tournament_id'])) {
            abort_unless($request->user()?->canAccessTournament((int) $data['tournament_id']), 403);
        }
        if (! empty($data['category_id'])) {
            $category = Category::query()->accessibleTo($request->user())->find($data['category_id']);
            abort_unless($category, 403, 'Esta categoría no está dentro de tu alcance.');
            $data['tournament_id'] = $data['tournament_id'] ?: $category->tournament_id;
        }

        $data['required'] = $request->boolean('required');
        $data['has_expiration'] = $request->boolean('has_expiration');

        return $data;
    }

    private function assertRequirementAccess(DocumentRequirement $requirement): void
    {
        abort_unless(
            $requirement->tournament_id === null || auth()->user()?->canAccessTournament((int) $requirement->tournament_id),
            403,
            'Este requisito no está dentro de tu alcance.'
        );
    }

    private function auditRequirement(DocumentRequirement $requirement, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Documentación',
            'action' => $action,
            'description' => $description,
            'auditable_type' => DocumentRequirement::class,
            'auditable_id' => $requirement->id,
            'metadata' => ['type' => $requirement->type],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function applyFilters(Request $request, $query, bool $skipStatus = false): void
    {
        $user = $request->user();

        $query
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('type', 'like', "%{$search}%")
                        ->orWhereHas('player', fn ($query) => $query->where(function ($query) use ($search) {
                            $query
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('document_number', 'like', "%{$search}%");
                        }))
                        ->orWhereHas('player.team', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('type') && $request->string('type')->toString() !== 'all', fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when(! $skipStatus && $request->filled('status') && $request->string('status')->toString() !== 'all', function ($query) use ($request) {
                $status = $request->string('status')->toString();

                if ($status === 'review') {
                    $query->whereIn('status', ['pending', 'observed']);

                    return;
                }

                $query->where('status', $status);
            })
            ->when($request->filled('delegation_id'), fn ($query) => $query->whereHas('player.team', fn ($query) => $query->where('delegation_id', $request->integer('delegation_id'))))
            ->when($request->filled('team_id'), fn ($query) => $query->whereHas('player', fn ($query) => $query->where('team_id', $request->integer('team_id'))))
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas('player.team', fn ($query) => $query->where('category_id', $request->integer('category_id'))))
            ->when($request->filled('tournament_id'), function ($query) use ($request, $user) {
                $tournamentId = $request->integer('tournament_id');
                abort_unless($user?->canAccessTournament($tournamentId), 403, 'Este torneo no está dentro de tu alcance.');
                $query->whereHas('player.team', fn ($query) => $query->where('tournament_id', $tournamentId));
            });
    }

    private function assertDocumentAccess(PlayerDocument $document): void
    {
        $tournamentId = $document->player?->team?->tournament_id;

        abort_unless(
            auth()->user()?->canAccessTournament($tournamentId !== null ? (int) $tournamentId : null),
            403,
            'Este documento no está dentro de tu alcance.'
        );
    }

    private function audit(PlayerDocument $document, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Documentación',
            'action' => $action,
            'description' => $description,
            'auditable_type' => PlayerDocument::class,
            'auditable_id' => $document->id,
            'metadata' => [
                'player' => $document->player?->fullName(),
                'type' => $document->type,
                'status' => $document->status,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
