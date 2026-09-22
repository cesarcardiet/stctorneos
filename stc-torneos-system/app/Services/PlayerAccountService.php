<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Support\PlayerCredentials;
use Illuminate\Support\Str;

class PlayerAccountService
{
    /**
     * @return array{user: User, created: bool, password: ?string}|null
     */
    public function ensureForPlayer(Player $player, ?int $invitedBy = null): ?array
    {
        $player->loadMissing(['team.tournament', 'team.category']);
        $email = Str::lower(trim((string) ($player->email ?? '')));

        if ($email === '') {
            return null;
        }

        $existing = User::query()->with('roles')->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing) {
            if ($this->blocksPlayerAccount($existing, $player)) {
                return null;
            }

            $this->attachPlayerRole($existing, $player, $invitedBy);
            $existing->fill([
                'name' => $player->fullName(),
                'status' => 'active',
                'player_id' => $player->id,
                'current_scope' => $player->team?->name ?: 'Mi ficha',
            ])->save();

            $this->syncTournamentScope($existing, $player);

            return [
                'user' => $existing->fresh(['roles']),
                'created' => false,
                'password' => null,
            ];
        }

        $plainPassword = PlayerCredentials::defaultPassword();
        $user = User::create([
            'name' => $player->fullName(),
            'email' => $email,
            'password' => $plainPassword,
            'status' => 'active',
            'email_verified_at' => now(),
            'player_id' => $player->id,
            'tournament_id' => $player->team?->tournament_id,
            'current_scope' => $player->team?->name ?: 'Mi ficha',
        ]);

        $this->attachPlayerRole($user, $player, $invitedBy);

        return [
            'user' => $user->fresh(['roles']),
            'created' => true,
            'password' => $plainPassword,
        ];
    }

    private function blocksPlayerAccount(User $user, Player $player): bool
    {
        if ($user->isSuperAdmin() || $user->hasRole('admin-torneo') || $user->hasRole('delegado')) {
            return true;
        }

        if ($user->hasRole('asistente-arbitro') || $user->hasRole('asistente-mesa')) {
            return true;
        }

        if ($user->hasRole('jugador') && $user->player_id && (int) $user->player_id !== (int) $player->id) {
            return true;
        }

        return false;
    }

    /**
     * Mensaje de error si el correo no puede usarse para el portal del jugador; null si está OK.
     */
    public function playerEmailIssue(string $email, Player $player): ?string
    {
        $email = Str::lower(trim($email));
        if ($email === '') {
            return null;
        }

        $existing = User::query()->with('roles')->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $existing) {
            return null;
        }

        if ($this->blocksPlayerAccount($existing, $player)) {
            return 'Ese correo ya está en uso por otro perfil del sistema. Usá un correo distinto para el jugador.';
        }

        return null;
    }

    private function attachPlayerRole(User $user, Player $player, ?int $invitedBy): bool
    {
        $role = Role::query()->where('slug', 'jugador')->first();
        if (! $role) {
            return false;
        }

        if (! $user->hasRole('jugador')) {
            $user->roles()->syncWithoutDetaching([
                $role->id => [
                    'scope_type' => 'system',
                    'scope_id' => null,
                    'assigned_by' => $invitedBy,
                    'assigned_at' => now(),
                ],
            ]);
            $user->unsetRelation('roles');
        }

        return true;
    }

    private function syncTournamentScope(User $user, Player $player): void
    {
        if ($user->tournament_id || ! $player->team?->tournament_id) {
            return;
        }

        $user->update([
            'tournament_id' => $player->team->tournament_id,
            'current_scope' => $player->team->name ?: $user->current_scope,
        ]);
    }
}
