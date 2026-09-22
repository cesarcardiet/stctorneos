<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Support\TutorCredentials;
use Illuminate\Support\Str;

class GuardianAccountService
{
    /**
     * @return array{user: User, created: bool, password: ?string}|null
     */
    public function ensureForGuardian(Player $player, string $email, ?string $name = null, ?int $invitedBy = null): ?array
    {
        $email = Str::lower(trim($email));
        if ($email === '') {
            return null;
        }

        $player->loadMissing(['team.tournament', 'guardian']);
        $guardianName = trim((string) ($name ?: $player->guardian?->name ?: 'Tutor de '.$player->fullName()));

        $existing = User::query()->with('roles')->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing) {
            if ($this->blocksTutorAccount($existing)) {
                return null;
            }

            $attached = $this->attachTutorRole($existing, $invitedBy);
            $existing->fill([
                'name' => $existing->name ?: $guardianName,
                'status' => 'active',
            ])->save();

            $this->syncTournamentScope($existing, $player);

            return [
                'user' => $existing->fresh(['roles']),
                'created' => false,
                'password' => null,
            ];
        }

        $plainPassword = TutorCredentials::defaultPassword();
        $user = User::create([
            'name' => $guardianName,
            'email' => $email,
            'password' => $plainPassword,
            'status' => 'active',
            'email_verified_at' => now(),
            'tournament_id' => $player->team?->tournament_id,
            'current_scope' => $player->team?->tournament?->name,
        ]);

        $this->attachTutorRole($user, $invitedBy);

        return [
            'user' => $user->fresh(['roles']),
            'created' => true,
            'password' => $plainPassword,
        ];
    }

    public function ensureForPlayer(Player $player, ?int $invitedBy = null): ?array
    {
        $player->loadMissing('guardian');
        $email = trim((string) ($player->guardian?->email ?? ''));

        if ($email === '') {
            return null;
        }

        return $this->ensureForGuardian(
            $player,
            $email,
            $player->guardian?->name,
            $invitedBy
        );
    }

    private function blocksTutorAccount(User $user): bool
    {
        if ($user->isSuperAdmin() || $user->hasRole('admin-torneo') || $user->hasRole('delegado')) {
            return true;
        }

        if ($user->hasRole('asistente-arbitro') || $user->hasRole('asistente-mesa')) {
            return true;
        }

        return $user->hasRole('jugador');
    }

    /**
     * Mensaje de error si el correo no puede usarse para el portal del tutor; null si está OK.
     */
    public function tutorEmailIssue(string $email, Player $player): ?string
    {
        $email = Str::lower(trim($email));
        if ($email === '') {
            return null;
        }

        $existing = User::query()->with('roles')->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $existing) {
            return null;
        }

        if ($existing->isSuperAdmin() || $existing->hasRole('admin-torneo') || $existing->hasRole('delegado')) {
            return 'Ese correo ya está en uso por otro perfil del sistema. Usá un correo distinto para el tutor.';
        }

        if ($existing->hasRole('asistente-arbitro') || $existing->hasRole('asistente-mesa')) {
            return 'Ese correo ya está en uso por otro perfil del sistema. Usá un correo distinto para el tutor.';
        }

        if ($existing->hasRole('jugador') && ! $existing->hasRole('tutor')
            && (int) $existing->player_id !== (int) $player->id) {
            return 'Ese correo ya está en uso por otro jugador. Usá un correo distinto para el tutor.';
        }

        return null;
    }

    private function attachTutorRole(User $user, ?int $invitedBy): bool
    {
        $role = Role::query()->where('slug', 'tutor')->first();
        if (! $role) {
            return false;
        }

        if ($user->hasRole('tutor')) {
            return false;
        }

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'scope_type' => 'system',
                'scope_id' => null,
                'assigned_by' => $invitedBy,
                'assigned_at' => now(),
            ],
        ]);
        $user->unsetRelation('roles');

        return true;
    }

    private function syncTournamentScope(User $user, Player $player): void
    {
        if ($user->tournament_id || ! $player->team?->tournament_id) {
            return;
        }

        $user->update([
            'tournament_id' => $player->team->tournament_id,
            'current_scope' => $player->team->tournament?->name,
        ]);
    }
}
