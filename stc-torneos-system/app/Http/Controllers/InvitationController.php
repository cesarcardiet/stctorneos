<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\Auth\InvitationRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationRegistrationService $registrationService,
    ) {}

    public function show(string $token): View|RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        if ($invitation instanceof RedirectResponse) {
            return $invitation;
        }

        return view('auth.invitation', [
            'invitation' => $invitation,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        if ($invitation instanceof RedirectResponse) {
            return $invitation;
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $this->registrationService->accept([
                'email' => $invitation->email,
                'name' => $invitation->name,
                'invitation_code' => $token,
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'] ?? $data['password'],
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('login')
            ->with('status', 'Cuenta activada. Ya podés ingresar con tu correo y la contraseña que definiste.');
    }

    private function findValidInvitation(string $token): Invitation|RedirectResponse
    {
        $invitation = Invitation::query()
            ->with('role')
            ->where(function ($query) use ($token) {
                $normalized = strtoupper(trim(str_replace([' ', '-'], '', $token)));
                $query
                    ->where('token', $token)
                    ->orWhere('code', $normalized)
                    ->orWhereRaw("REPLACE(UPPER(code), '-', '') = ?", [$normalized]);
            })
            ->first();

        if (! $invitation) {
            return redirect()->route('login')->withErrors(['email' => 'La invitación no existe o ya fue usada.']);
        }

        if ($invitation->accepted_at || $invitation->status === 'accepted') {
            return redirect()->route('login')->with('status', 'Esta invitación ya fue activada. Podés ingresar normalmente.');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            return redirect()->route('login')->withErrors(['email' => 'La invitación expiró. Pedile a un administrador que genere una nueva.']);
        }

        if (! $invitation->isMobileOnboardable()) {
            return redirect()->route('login')->withErrors(['email' => 'Esta invitación debe completarse desde el enlace web especializado.']);
        }

        return $invitation;
    }
}
