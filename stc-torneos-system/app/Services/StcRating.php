<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FixtureMatch;
use App\Models\Player;
use Illuminate\Support\Collection;

class StcRating
{
    public function __construct(private readonly CompetitionBoard $board) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forCategory(Category $category, ?string $round = null): Collection
    {
        $category->loadMissing(['teams.players']);
        $rows = [];

        foreach ($category->teams as $team) {
            foreach ($team->players as $player) {
                $rows[$player->id] = $this->emptyRow($player, $team->name);
            }
        }

        $matches = $this->board->closedMatches($category)
            ->when($round, fn (Collection $items) => $items->filter(
                fn (FixtureMatch $match) => (string) $match->round === (string) $round
            ));

        foreach ($matches as $match) {
            $this->applyMatch($rows, $match);
        }

        return collect($rows)
            ->filter(fn (array $row) => $row['points'] !== 0 || $row['matches'] > 0)
            ->sortByDesc(fn (array $row) => $row['points'])
            ->values()
            ->map(function (array $row, int $index) {
                $row['position'] = $index + 1;

                return $row;
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function teamOfRound(Category $category, string $round, int $limit = 11): Collection
    {
        return $this->forCategory($category, $round)->take($limit)->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyMatch(array &$rows, FixtureMatch $match): void
    {
        $match->loadMissing(['sheet.events.player.team', 'homeTeam.players', 'awayTeam.players']);
        [$homeScore, $awayScore] = $match->officialScores();
        $homeWon = (int) $homeScore > (int) $awayScore;
        $awayWon = (int) $awayScore > (int) $homeScore;
        $homeClean = (int) $awayScore === 0;
        $awayClean = (int) $homeScore === 0;
        $participants = [];

        foreach ($match->sheet?->events ?? [] as $event) {
            $playerId = $event->player_id;
            if (! $playerId || ! isset($rows[$playerId])) {
                continue;
            }

            $participants[$playerId] = $event->player?->team_id ?? $event->team_id;
            $delta = match ($event->type) {
                'goal' => 4,
                'assist' => 3,
                'motm' => 5,
                'yellow' => -1,
                'red' => -3,
                default => 0,
            };

            $rows[$playerId]['points'] += $delta;
            $rows[$playerId]['matches'] = max($rows[$playerId]['matches'], 1);
            $rows[$playerId]['breakdown'][$event->type] = ($rows[$playerId]['breakdown'][$event->type] ?? 0) + 1;
        }

        foreach ([$match->homeTeam, $match->awayTeam] as $index => $team) {
            $won = $index === 0 ? $homeWon : $awayWon;
            $clean = $index === 0 ? $homeClean : $awayClean;

            foreach ($team?->players ?? [] as $player) {
                if (! isset($rows[$player->id])) {
                    continue;
                }

                $played = isset($participants[$player->id]);
                if (! $played) {
                    continue;
                }

                $rows[$player->id]['matches'] = max($rows[$player->id]['matches'], 1);

                if ($won) {
                    $rows[$player->id]['points'] += 1;
                    $rows[$player->id]['breakdown']['win'] = ($rows[$player->id]['breakdown']['win'] ?? 0) + 1;
                }

                if ($clean && $this->isGoalkeeper($player)) {
                    $rows[$player->id]['points'] += 3;
                    $rows[$player->id]['breakdown']['clean_sheet'] = ($rows[$player->id]['breakdown']['clean_sheet'] ?? 0) + 1;
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRow(Player $player, string $teamName): array
    {
        return [
            'player' => $player,
            'team' => $teamName,
            'position' => $player->position,
            'points' => 0,
            'matches' => 0,
            'breakdown' => [],
        ];
    }

    private function isGoalkeeper(Player $player): bool
    {
        return (bool) preg_match('/arquer|porter|gk|arquero/i', (string) $player->position);
    }
}
