<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\RosterShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RosterShareController extends Controller
{
    public function show(string $token, RosterShareService $rosterShare): View
    {
        $invitation = $rosterShare->usableInvitation($token);
        $team = $rosterShare->resolveTeam($token);

        if ($invitation->family_status === 'generated') {
            $invitation->update([
                'family_status' => 'accessed',
                'accessed_at' => $invitation->accessed_at ?: now(),
            ]);
        }

        $category = $team->category;
        $locked = ! $category?->acceptsRosterEdits($team);
        $lockReason = $locked ? $category?->rosterEditBlockedReason($team) : null;

        return view('plantel.show', [
            'invitation' => $invitation->fresh(),
            'team' => $team,
            'category' => $category,
            'tournament' => $category?->tournament,
            'locked' => $locked,
            'lockReason' => $lockReason,
        ]);
    }

    public function store(Request $request, string $token, RosterShareService $rosterShare): RedirectResponse
    {
        $invitation = $rosterShare->usableInvitation($token);
        $team = $rosterShare->resolveTeam($token);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:40'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_phone' => ['nullable', 'string', 'max:80'],
            'guardian_email' => ['nullable', 'email', 'max:180'],
        ]);

        $player = $rosterShare->addPlayer($team, $data);

        if ($invitation->family_status !== 'completed') {
            $invitation->update(['family_status' => 'in_progress']);
        }

        return back()->with('status', $player->fullName().' quedó cargado en el plantel.');
    }
}
