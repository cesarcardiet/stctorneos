<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\ContentPost;
use App\Models\FixtureMatch;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends ApiController
{
    public function home(): JsonResponse
    {
        $tournament = Tournament::query()
            ->where('visibility', 'public')
            ->whereIn('status', ['registration', 'preparation', 'in_progress'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'preparation' THEN 1 WHEN 'registration' THEN 2 ELSE 3 END")
            ->orderByDesc('starts_at')
            ->first();

        $live = FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'category.tournament'])
            ->where('status', 'live')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (FixtureMatch $match) => $this->matchPayload($match));

        $news = ContentPost::query()
            ->where('status', 'published')
            ->whereIn('audience', ['public', 'all'])
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(fn (ContentPost $post) => $this->postPayload($post));

        $upcoming = FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'category.tournament'])
            ->where('published', true)
            ->whereIn('status', ['scheduled', 'live'])
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get()
            ->map(fn (FixtureMatch $match) => $this->matchPayload($match));

        return $this->ok([
            'tournament' => $tournament ? $this->tournamentPayload($tournament) : null,
            'live_matches' => $live->values(),
            'upcoming' => $upcoming->values(),
            'news' => $news->values(),
        ]);
    }

    public function tournaments(): JsonResponse
    {
        $tournaments = Tournament::query()
            ->where('visibility', 'public')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Tournament $tournament) => $this->tournamentPayload($tournament));

        return $this->ok($tournaments->values());
    }

    public function tournament(Tournament $tournament): JsonResponse
    {
        $tournament->load(['categories' => fn ($query) => $query->orderBy('name')]);

        return $this->ok([
            ...$this->tournamentPayload($tournament),
            'categories' => $tournament->categories->map(fn (Category $category) => $this->categoryPayload($category))->values(),
        ]);
    }

    public function category(Category $category): JsonResponse
    {
        $category->load(['tournament', 'teams.delegation']);

        return $this->ok([
            ...$this->categoryPayload($category),
            'teams' => $category->teams->map(fn (Team $team) => $this->teamPayload($team))->values(),
        ]);
    }

    public function matches(Request $request): JsonResponse
    {
        $matches = FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'category.tournament'])
            ->where(function ($query) {
                $query->where('published', true)->orWhereIn('status', ['live', 'finished', 'validated']);
            })
            ->when($request->filled('tournament_id'), fn ($query) => $query->where('tournament_id', $request->integer('tournament_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('status') && $request->string('status')->toString() !== 'all', fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (FixtureMatch $match) => $this->matchPayload($match));

        return $this->ok($matches->values());
    }

    public function match(FixtureMatch $match): JsonResponse
    {
        $match->load(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'category.tournament', 'sheet', 'lineups.player']);

        return $this->ok([
            ...$this->matchPayload($match),
            'referee' => $match->referee_name,
            'notes' => $match->published ? $match->notes : null,
            'lineups' => $match->lineups->map(fn ($row) => [
                'team_id' => $row->team_id,
                'starter' => (bool) $row->starter,
                'sort_order' => $row->sort_order,
                'player' => $row->player ? [
                    'id' => $row->player->id,
                    'name' => $row->player->fullName(),
                    'jersey' => $row->player->jersey_number,
                    'position' => $row->player->position,
                ] : null,
            ])->values(),
        ]);
    }

    public function team(Team $team): JsonResponse
    {
        $team->load(['category.tournament', 'delegation', 'players']);

        return $this->ok([
            ...$this->teamPayload($team),
            'players' => $team->players->map(fn (Player $player) => $this->playerPayload($player))->values(),
        ]);
    }

    public function player(Player $player): JsonResponse
    {
        $player->load(['team.category', 'team.delegation']);

        return $this->ok($this->playerPayload($player));
    }
}
