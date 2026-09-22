<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Tournament;
use App\Models\User;

class WorkspaceContext
{
    public const SESSION_KEY = 'workspace_tournament_id';

    public const CATEGORY_SESSION_KEY = 'workspace_category_id';

    public static function rememberTournament(?Tournament $tournament): void
    {
        if ($tournament) {
            session([self::SESSION_KEY => (int) $tournament->id]);
        }
    }

    public static function rememberCategory(?Category $category): void
    {
        if ($category?->tournament_id) {
            self::rememberTournament($category->tournament ?? Tournament::query()->find($category->tournament_id));
            session([self::CATEGORY_SESSION_KEY => (int) $category->id]);
        }
    }

    public static function activeCategory(?User $user, ?Tournament $tournament = null): ?Category
    {
        $tournament ??= self::activeTournament($user);
        if (! $user || ! $tournament) {
            return null;
        }

        $sessionId = (int) session(self::CATEGORY_SESSION_KEY, 0);
        if ($sessionId > 0) {
            $found = Category::query()->find($sessionId);
            if ($found && (int) $found->tournament_id === (int) $tournament->id && $user->canAccessTournament((int) $found->tournament_id)) {
                return $found;
            }
        }

        if ($user->isPlayerAccount()) {
            return self::playerCompetitionCategory($user, $tournament);
        }

        return $tournament->categories()->orderBy('sort_order')->orderBy('name')->first();
    }

    public static function activeTournament(?User $user): ?Tournament
    {
        if (! $user) {
            return null;
        }

        $sessionId = (int) session(self::SESSION_KEY, 0);
        if ($sessionId > 0 && $user->canAccessTournament($sessionId)) {
            return Tournament::query()->find($sessionId);
        }

        if ($user->isPlayerAccount()) {
            return $user->linkedPlayer()?->team?->tournament;
        }

        if ($user->isTutorAccount()) {
            $ids = $user->tutorTournamentIds();
            if ($ids !== []) {
                return Tournament::query()->find($ids[0]);
            }
        }

        return $user->tournament;
    }

    /**
     * Categoría de competencia del jugador solo si pertenece al torneo activo.
     */
    public static function playerCompetitionCategory(?User $user, ?Tournament $activeTournament): ?Category
    {
        if (! $user?->isPlayerAccount()) {
            return null;
        }

        $own = $user->playerCategory();
        if (! $own || ! $activeTournament) {
            return $own;
        }

        if ((int) $own->tournament_id === (int) $activeTournament->id) {
            return $own;
        }

        return null;
    }
}
