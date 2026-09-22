<?php

namespace App\Services;

use App\Models\FixtureMatch;
use App\Models\MatchSheetEvent;
use App\Models\Player;
use Illuminate\Support\Collection;

class PlayerPerformanceService
{
    public function __construct(private CompetitionBoard $board) {}

    /**
     * @return array{
     *     goals: int,
     *     assists: int,
     *     yellows: int,
     *     reds: int,
     *     matches_played: int,
     *     team_matches: int,
     *     upcoming: int
     * }
     */
    public function summary(Player $player): array
    {
        $player->loadMissing('team.category');
        $category = $player->team?->category;

        if (! $category) {
            return [
                'goals' => 0,
                'assists' => 0,
                'yellows' => 0,
                'reds' => 0,
                'matches_played' => 0,
                'team_matches' => 0,
                'upcoming' => 0,
            ];
        }

        $events = $this->playerEvents($player, $category->id);
        $matchIds = $events
            ->map(fn (MatchSheetEvent $event) => $event->sheet?->match_id)
            ->filter()
            ->unique();

        $teamMatches = $this->teamMatches($player);
        $closedTeamMatches = $teamMatches->filter(fn (FixtureMatch $match) => $this->board->isClosed($match));

        return [
            'goals' => $events->where('type', 'goal')->count(),
            'assists' => $events->where('type', 'assist')->count(),
            'yellows' => $events->where('type', 'yellow')->count(),
            'reds' => $events->where('type', 'red')->count(),
            'matches_played' => $matchIds->count(),
            'team_matches' => $closedTeamMatches->count(),
            'upcoming' => $teamMatches->filter(fn (FixtureMatch $match) => in_array($match->status, ['scheduled', 'rescheduled', 'live'], true))->count(),
        ];
    }

    /**
     * @return Collection<int, array{
     *     match: FixtureMatch,
     *     events: Collection<int, MatchSheetEvent>,
     *     result_label: string,
     *     opponent: string,
     *     is_home: bool
     * }>
     */
    public function matchHistory(Player $player): Collection
    {
        $player->loadMissing('team');

        return $this->teamMatches($player)
            ->sortByDesc(fn (FixtureMatch $match) => $match->scheduled_at?->timestamp ?? 0)
            ->map(function (FixtureMatch $match) use ($player) {
                $teamId = (int) $player->team_id;
                $isHome = (int) $match->home_team_id === $teamId;
                $opponent = $isHome ? $match->awayTeam?->name : $match->homeTeam?->name;
                $events = $match->sheet?->events
                    ->where('player_id', $player->id)
                    ->values() ?? collect();

                $homeScore = $match->sheet?->home_score ?? $match->home_score;
                $awayScore = $match->sheet?->away_score ?? $match->away_score;

                return [
                    'match' => $match,
                    'events' => $events,
                    'result_label' => $this->resultLabel($match, $teamId, $homeScore, $awayScore),
                    'opponent' => $opponent ?: 'Rival',
                    'is_home' => $isHome,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, MatchSheetEvent>
     */
    public function recentEvents(Player $player, int $limit = 12): Collection
    {
        $player->loadMissing('team.category');
        $categoryId = $player->team?->category_id;

        if (! $categoryId) {
            return collect();
        }

        return $this->playerEvents($player, (int) $categoryId)
            ->sortByDesc(fn (MatchSheetEvent $event) => $event->sheet?->match?->scheduled_at?->timestamp ?? 0)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, MatchSheetEvent>
     */
    private function playerEvents(Player $player, int $categoryId): Collection
    {
        return MatchSheetEvent::query()
            ->with(['sheet.match.homeTeam', 'sheet.match.awayTeam', 'sheet.match.field', 'team'])
            ->where('player_id', $player->id)
            ->whereHas('sheet.match', function ($query) use ($categoryId) {
                $query
                    ->where('category_id', $categoryId)
                    ->whereIn('status', $this->board->closedStatuses());
            })
            ->get();
    }

    /**
     * @return Collection<int, FixtureMatch>
     */
    private function teamMatches(Player $player): Collection
    {
        $teamId = $player->team_id;

        if (! $teamId) {
            return collect();
        }

        return FixtureMatch::query()
            ->with([
                'homeTeam.delegation',
                'awayTeam.delegation',
                'field',
                'sheet.events.player',
            ])
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->orderByDesc('scheduled_at')
            ->get();
    }

    private function resultLabel(FixtureMatch $match, int $teamId, mixed $homeScore, mixed $awayScore): string
    {
        if ($homeScore === null && $awayScore === null) {
            return $match->statusLabel();
        }

        $homeScore = (int) $homeScore;
        $awayScore = (int) $awayScore;
        $teamScore = (int) $match->home_team_id === $teamId ? $homeScore : $awayScore;
        $rivalScore = (int) $match->home_team_id === $teamId ? $awayScore : $homeScore;

        if ($teamScore > $rivalScore) {
            return 'Victoria '.$teamScore.':'.$rivalScore;
        }

        if ($teamScore < $rivalScore) {
            return 'Derrota '.$teamScore.':'.$rivalScore;
        }

        return 'Empate '.$teamScore.':'.$rivalScore;
    }
}
