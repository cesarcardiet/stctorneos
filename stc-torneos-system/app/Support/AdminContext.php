<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;

class AdminContext
{
    public const SESSION_KEY = 'admin_tournament_id';

    public const CATEGORY_SESSION_KEY = 'admin_category_id';

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

    public static function activeTournament(?User $user): ?Tournament
    {
        if (! $user) {
            return null;
        }

        $sessionId = (int) session(self::SESSION_KEY, 0);
        if ($sessionId > 0 && $user->canAccessTournament($sessionId)) {
            return Tournament::query()->find($sessionId);
        }

        return $user->tournament ?? $user->accessibleTournaments()->first();
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

        return $tournament->categories()->orderBy('sort_order')->orderBy('name')->first();
    }

    public static function resolveTournamentId(Request $request): ?int
    {
        $user = $request->user();
        $param = $request->string('tournament_id')->toString();

        if ($param !== '') {
            $id = (int) $param;
            if ($user?->canAccessTournament($id)) {
                $tournament = Tournament::query()->find($id);
                if ($tournament) {
                    self::rememberTournament($tournament);
                }

                return $id;
            }
        }

        return self::activeTournament($user)?->id;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function applyTournamentFilter(Request $request, array $filters): array
    {
        $tournamentId = self::resolveTournamentId($request);
        if ($tournamentId && ($filters['tournament_id'] ?? '') === '') {
            $filters['tournament_id'] = (string) $tournamentId;
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public static function tournamentQuery(?Tournament $tournament, array $query = []): array
    {
        if ($tournament) {
            $query['tournament_id'] = $tournament->id;
        }

        return $query;
    }
}
