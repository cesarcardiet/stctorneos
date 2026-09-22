<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Services\CompetitionBoard;
use Illuminate\Http\JsonResponse;

class BoardController extends ApiController
{
    public function __construct(private readonly CompetitionBoard $board) {}

    public function standings(Category $category): JsonResponse
    {
        $groups = $this->board->standingsByGroup($category)->map(function ($rows, string $group) {
            return [
                'group' => $group,
                'rows' => $rows->map(fn (array $row) => [
                    'position' => $row['position'],
                    'team' => $this->teamPayload($row['team']),
                    'points' => $row['points'],
                    'played' => $row['played'],
                    'won' => $row['won'],
                    'drawn' => $row['drawn'],
                    'lost' => $row['lost'],
                    'gf' => $row['gf'],
                    'ga' => $row['ga'],
                    'gd' => $row['gd'],
                    'percent' => $row['percent'],
                    'pending' => $row['pending'],
                ])->values(),
            ];
        })->values();

        return $this->ok([
            'category' => $this->categoryPayload($category->loadMissing('tournament')),
            'groups' => $groups,
        ]);
    }

    public function rankings(Category $category): JsonResponse
    {
        $rankings = collect($this->board->playerRankings($category))->map(function ($players, string $title) {
            return [
                'title' => $title,
                'players' => $players->map(fn (array $row) => [
                    'name' => $row[0],
                    'team' => $row[1],
                    'value' => $row[2],
                    'photo' => $row[3] ?? null,
                ])->values(),
            ];
        })->values();

        $vallas = $this->board->leastGoalsAgainst($category)->map(fn (array $row) => [
            'position' => $row['position'],
            'team' => $this->teamPayload($row['team']),
            'played' => $row['played'],
            'gf' => $row['gf'],
            'ga' => $row['ga'],
        ])->values();

        return $this->ok([
            'category' => $this->categoryPayload($category->loadMissing('tournament')),
            'rankings' => $rankings,
            'least_goals_against' => $vallas,
        ]);
    }

    public function fairPlay(Category $category): JsonResponse
    {
        $rows = $this->board->fairPlay($category)->map(fn (array $row) => [
            'position' => $row['position'],
            'team' => $this->teamPayload($row['team']),
            'yellows' => $row['yellows'],
            'reds' => $row['reds'],
            'staff_yellow' => $row['staff_yellow'],
            'staff_expulsion' => $row['staff_expulsion'],
            'family_misconduct' => $row['family_misconduct'],
            'family_expulsion' => $row['family_expulsion'],
            'adult_serious' => $row['adult_serious'],
            'late_arrival' => $row['late_arrival'],
            'adult_incidents' => $row['adult_incidents'],
            'incidents' => $row['incidents'],
            'points' => $row['points'],
        ])->values();

        return $this->ok([
            'category' => $this->categoryPayload($category->loadMissing('tournament')),
            'rows' => $rows,
        ]);
    }

    public function brackets(Category $category): JsonResponse
    {
        $brackets = $this->board->brackets($category)->map(function ($matches, string $stage) {
            return [
                'stage' => $stage,
                'matches' => $matches->map(fn ($match) => $this->matchPayload($match->loadMissing(['homeTeam', 'awayTeam', 'field', 'category'])))->values(),
            ];
        })->values();

        return $this->ok([
            'category' => $this->categoryPayload($category->loadMissing('tournament')),
            'brackets' => $brackets,
        ]);
    }
}
