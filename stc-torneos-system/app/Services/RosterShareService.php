<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RosterShareService
{
    public function activeForTeam(Team $team): ?Invitation
    {
        return Invitation::query()
            ->where('kind', 'roster')
            ->where('scope_type', 'team')
            ->where('scope_id', $team->id)
            ->whereNotIn('family_status', ['invalidated'])
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    public function create(Team $team, User $inviter): Invitation
    {
        $this->invalidateActiveForTeam($team);

        return Invitation::create([
            'name' => 'Plantel · '.$team->name,
            'email' => 'plantel+'.$team->id.'@enlace.stc',
            'status' => 'active',
            'kind' => 'roster',
            'family_status' => 'generated',
            'token' => Invitation::makeToken(),
            'scope_type' => 'team',
            'scope_id' => $team->id,
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function regenerate(Team $team, User $inviter): Invitation
    {
        return $this->create($team, $inviter);
    }

    public function invalidate(Invitation $invitation): void
    {
        $invitation->update([
            'family_status' => 'invalidated',
            'status' => 'invalidated',
        ]);
    }

    public function invalidateActiveForTeam(Team $team): void
    {
        Invitation::query()
            ->where('kind', 'roster')
            ->where('scope_type', 'team')
            ->where('scope_id', $team->id)
            ->whereNotIn('family_status', ['invalidated'])
            ->update([
                'family_status' => 'invalidated',
                'status' => 'invalidated',
            ]);
    }

    public function resolveTeam(string $token): Team
    {
        $invitation = $this->usableInvitation($token);
        $team = Team::query()
            ->with(['category.tournament', 'delegation', 'players' => fn ($q) => $q->orderBy('last_name')->orderBy('first_name')])
            ->find($invitation->scope_id);

        abort_unless($team, 404, 'Este enlace ya no tiene un equipo válido.');

        return $team;
    }

    public function usableInvitation(string $token): Invitation
    {
        $invitation = Invitation::query()->where('token', $token)->firstOrFail();

        abort_unless($invitation->isRoster(), 404, 'Enlace no válido.');
        abort_unless($invitation->isRosterUsable(), 403, 'Este enlace de plantel ya no está disponible.');

        return $invitation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addPlayer(Team $team, array $data): Player
    {
        $team->loadMissing('category.tournament');
        $category = $team->category;

        abort_unless($category, 422, 'El equipo no tiene categoría asignada.');
        abort_unless($category->acceptsRosterEdits($team), 403, $category->rosterEditBlockedReason($team));

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        if ($firstName === '' || $lastName === '') {
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
            'document_number' => filled($data['document_number'] ?? null) ? $data['document_number'] : null,
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
            'guardian_relationship' => $data['guardian_relationship'] ?? Guardian::relationshipOptions()[0] ?? 'Padre',
        ]);

        return $player->fresh(['guardian']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function splitPlayerName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        if (count($parts) <= 1) {
            return [$parts[0] ?? '', ''];
        }

        $firstName = array_pop($parts);

        return [implode(' ', $parts), $firstName];
    }
}
