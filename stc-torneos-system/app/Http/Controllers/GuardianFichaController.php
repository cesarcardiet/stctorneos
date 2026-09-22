<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\GuardianFichaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardianFichaController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->viewableInvitation($token);
        $player = $invitation->player()->with(['team.category', 'team.tournament', 'guardian', 'documents'])->firstOrFail();
        $player->ensureDocuments();

        $invitation = app(GuardianFichaService::class)->markAccessed($invitation);

        return view('ficha.show', [
            'invitation' => $invitation,
            'player' => $player->fresh(['team.category', 'team.tournament', 'guardian', 'documents']),
            'guardian' => $player->guardian ?? new \App\Models\Guardian(['relationship' => 'Madre']),
            'locked' => $invitation->family_status === 'completed',
            'fichaSubmitUrl' => route('ficha.update', $invitation->token),
            'fichaCheckEmailUrl' => route('ficha.check-email', $invitation->token),
            'authAccepted' => [],
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->usableInvitation($token);

        return app(GuardianFichaService::class)->submit($request, $invitation);
    }

    public function checkEmail(Request $request, string $token): \Illuminate\Http\JsonResponse
    {
        $invitation = $this->viewableInvitation($token);
        $player = $invitation->player()->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
            'kind' => ['nullable', 'in:tutor,player'],
        ]);

        $email = strtolower(trim($data['email']));
        $kind = $data['kind'] ?? 'tutor';

        if ($kind === 'player') {
            $issue = app(\App\Services\PlayerAccountService::class)->playerEmailIssue($email, $player);

            return response()->json([
                'ok' => $issue === null,
                'message' => $issue,
                'field' => 'player_email',
            ]);
        }

        // Formato válido alcanza para el tutor: el envío de ficha no exige cuenta tutor nueva.
        return response()->json([
            'ok' => true,
            'message' => null,
            'field' => 'email',
        ]);
    }

    private function usableInvitation(string $token): Invitation
    {
        $invitation = Invitation::query()
            ->where('token', $token)
            ->where('kind', 'guardian')
            ->firstOrFail();

        abort_unless($invitation->isUsable(), 410, 'Este enlace ya no está vigente.');
        abort_unless($invitation->player_id, 404);

        return $invitation;
    }

    private function viewableInvitation(string $token): Invitation
    {
        $invitation = Invitation::query()
            ->where('token', $token)
            ->where('kind', 'guardian')
            ->firstOrFail();

        abort_unless($invitation->canViewFicha(), 410, 'Este enlace ya no está vigente.');
        abort_unless($invitation->player_id, 404);

        return $invitation;
    }
}

