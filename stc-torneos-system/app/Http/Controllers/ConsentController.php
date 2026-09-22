<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\PlayerEmailNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsentController extends Controller
{
    public function show(Request $request, Player $player): View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de autorización expiró o no es válido.');
        }

        $player->load(['team.category.tournament', 'guardian']);

        return view('consent.show', [
            'player' => $player,
        ]);
    }

    public function store(Request $request, Player $player): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de autorización expiró o no es válido.');
        }

        $data = $request->validate([
            'consent_status' => ['required', 'in:approved,rejected'],
        ]);

        abort_unless($player->guardian, 404, 'Esta ficha no tiene tutor cargado.');

        $player->guardian->update([
            'consent_status' => $data['consent_status'],
        ]);

        if ($data['consent_status'] === 'approved' && $player->status === 'awaiting_guardian') {
            $player->update(['status' => 'in_progress']);
        }

        if ($data['consent_status'] === 'rejected') {
            $player->update(['status' => 'observed', 'observation_reason' => 'Autorización rechazada por el tutor.']);
        }

        PlayerEmailNotifier::statusChanged(
            $player->fresh(['guardian', 'team.category.tournament']),
            $data['consent_status'] === 'approved'
                ? 'Autorización registrada correctamente.'
                : 'Autorización rechazada por el tutor.'
        );

        return back()->with('status', $data['consent_status'] === 'approved'
            ? 'Gracias. La autorización quedó registrada.'
            : 'Quedó registrado que no autorizás la ficha en este momento.');
    }
}
