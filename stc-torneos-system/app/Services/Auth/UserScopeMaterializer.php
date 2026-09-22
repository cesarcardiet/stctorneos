<?php

namespace App\Services\Auth;

use App\Models\Category;
use App\Models\Delegation;
use App\Models\FixtureMatch;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\User;
use App\Support\InvitationScope;

class UserScopeMaterializer
{
    public function apply(User $user, Invitation $invitation, ?int $assignedBy = null): void
    {
        $scopeType = InvitationScope::normalize($invitation->scope_type);
        $scopeId = $invitation->scope_id ? (int) $invitation->scope_id : null;
        $assignedBy = $assignedBy ?? $invitation->invited_by;

        if ($invitation->role_id) {
            $user->roles()->syncWithoutDetaching([
                $invitation->role_id => [
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'assigned_by' => $assignedBy,
                    'assigned_at' => now(),
                ],
            ]);
        }

        match ($scopeType) {
            InvitationScope::TOURNAMENT => $this->applyTournament($user, $scopeId),
            InvitationScope::CATEGORY => $this->applyCategory($user, $scopeId),
            InvitationScope::DELEGATION => $this->applyDelegation($user, $scopeId),
            InvitationScope::PLAYER => $this->applyPlayer($user, $scopeId ?? $invitation->player_id),
            InvitationScope::MATCH => $this->applyMatch($user, $scopeId),
            default => null,
        };

        if ($scopeType === InvitationScope::SYSTEM) {
            $user->forceFill(['current_scope' => $user->current_scope ?: 'Sistema'])->save();
        }
    }

    private function applyTournament(User $user, ?int $tournamentId): void
    {
        if (! $tournamentId) {
            return;
        }

        $tournament = \App\Models\Tournament::query()->find($tournamentId);
        $user->forceFill([
            'tournament_id' => $tournamentId,
            'current_scope' => $tournament?->name ?: $user->current_scope,
        ])->save();
    }

    private function applyCategory(User $user, ?int $categoryId): void
    {
        if (! $categoryId) {
            return;
        }

        $category = Category::query()->with('tournament')->find($categoryId);
        $user->forceFill([
            'tournament_id' => $category?->tournament_id,
            'current_scope' => $category?->name ?: $user->current_scope,
        ])->save();
    }

    private function applyDelegation(User $user, ?int $delegationId): void
    {
        if (! $delegationId) {
            return;
        }

        $delegation = Delegation::query()->with('tournament')->find($delegationId);
        $user->forceFill([
            'delegation_id' => $delegationId,
            'tournament_id' => $delegation?->tournament_id,
            'current_scope' => $delegation?->name ?: $user->current_scope,
        ])->save();

        $user->assignedDelegations()->syncWithoutDetaching([$delegationId]);
    }

    private function applyPlayer(User $user, ?int $playerId): void
    {
        if (! $playerId) {
            return;
        }

        $player = Player::query()->with('team.tournament')->find($playerId);
        $user->forceFill([
            'player_id' => $playerId,
            'tournament_id' => $player?->team?->tournament_id,
            'current_scope' => $player?->team?->name ?: $user->current_scope,
        ])->save();
    }

    private function applyMatch(User $user, ?int $matchId): void
    {
        if (! $matchId) {
            return;
        }

        $match = FixtureMatch::query()->with('category.tournament')->find($matchId);
        $user->forceFill([
            'tournament_id' => $match?->category?->tournament_id,
            'current_scope' => $match?->title() ?: $user->current_scope,
        ])->save();
    }
}
