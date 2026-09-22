<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FixtureMatch;
use App\Models\MatchSheetEvent;
use App\Models\MatchSheetIncident;
use App\Models\Player;
use App\Models\Sanction;
use App\Models\Team;
use App\Support\CategoryWorkspace;
use App\Support\FairPlayRules;
use Illuminate\Support\Collection;

class CompetitionBoard
{
    /**
     * @return list<string>
     */
    public function closedStatuses(): array
    {
        return ['finished', 'validated'];
    }

    public function isClosed(FixtureMatch $match): bool
    {
        return in_array($match->status, $this->closedStatuses(), true);
    }

    public function isKnockout(FixtureMatch $match): bool
    {
        return (bool) preg_match('/semi|final|cuartos|octavos|playoff/i', (string) $match->stage);
    }

    public function isGroupStage(FixtureMatch $match): bool
    {
        return ! $this->isKnockout($match);
    }

    /**
     * @return Collection<int, FixtureMatch>
     */
    public function closedMatches(Category $category): Collection
    {
        return FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'sheet'])
            ->where('category_id', $category->id)
            ->whereIn('status', $this->closedStatuses())
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function standingsByGroup(Category $category): Collection
    {
        $category->loadMissing(['teams.delegation']);
        $fairPlay = $this->fairPlay($category)->keyBy(fn (array $row) => $row['team']->id);
        $counted = $this->countedMatches($category);
        $pending = FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'sheet'])
            ->where('category_id', $category->id)
            ->where('status', '!=', 'suspended')
            ->get()
            ->filter(fn (FixtureMatch $match) => ! $match->countsForStandings());

        $rows = [];
        foreach ($category->teams as $team) {
            $rows[$team->id] = $this->emptyStanding($team);
        }

        foreach ($counted as $match) {
            if (! $this->isGroupStage($match)) {
                continue;
            }

            $this->applyMatch($rows, $match, $category);
        }

        foreach ($pending as $match) {
            if (! $this->isGroupStage($match)) {
                continue;
            }

            if (isset($rows[$match->home_team_id])) {
                $rows[$match->home_team_id]['pending']++;
            }
            if (isset($rows[$match->away_team_id])) {
                $rows[$match->away_team_id]['pending']++;
            }
        }

        $pointsWin = max(1, (int) $category->points_win);

        foreach (Sanction::query()->active()->where('category_id', $category->id)->get() as $sanction) {
            if ($sanction->team_id && isset($rows[$sanction->team_id])) {
                $rows[$sanction->team_id]['points'] += (int) $sanction->points_delta;
                $rows[$sanction->team_id]['sanctions'] = ($rows[$sanction->team_id]['sanctions'] ?? 0) + (int) $sanction->points_delta;
            }
        }

        return collect($rows)
            ->map(function (array $row) use ($pointsWin, $fairPlay) {
                $row['gd'] = $row['gf'] - $row['ga'];
                $row['percent'] = $row['played'] > 0
                    ? (int) round(($row['points'] / ($row['played'] * $pointsWin)) * 100)
                    : 0;
                $row['fair_play'] = (int) ($fairPlay[$row['team']->id]['points'] ?? 0);

                return $row;
            })
            ->groupBy(fn (array $row) => $this->groupLabel($row['team']))
            ->map(function (Collection $group, string $label) use ($category) {
                $workspace = $category->workspace();
                $groupKey = CategoryWorkspace::groupLabelToOrderKey($label);

                return CategoryWorkspace::applyTeamDisplayOrder($this->sortStandings($group, $category), $workspace, $groupKey)
                    ->values()
                    ->map(function (array $row, int $index) {
                        $row['position'] = $index + 1;

                        return $row;
                    });
            })
            ->pipe(fn (Collection $groups) => $this->ensureConfiguredGroups($groups, $category))
            ->pipe(fn (Collection $groups) => $this->orderGroups($groups, $category));
    }

    /**
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $groups
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function ensureConfiguredGroups(Collection $groups, Category $category): Collection
    {
        $count = (int) $category->groups_count;
        if ($count < 1) {
            return $groups;
        }

        $letters = $category->groupLetters();

        foreach ($letters as $letter) {
            $label = 'Grupo '.$letter;
            if (! $groups->has($label)) {
                $groups->put($label, collect());
            }
        }

        return $groups;
    }

    /**
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $groups
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function orderGroups(Collection $groups, Category $category): Collection
    {
        $configured = collect($category->workspace()['group_order'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();

        if ($configured->isNotEmpty()) {
            return $groups->sortBy(function ($rows, $key) use ($configured) {
                $label = $key === 'Sin grupo'
                    ? 'Sin grupo'
                    : (str_starts_with($key, 'Grupo ') ? substr($key, strlen('Grupo ')) : $key);
                $index = $configured->search(function (string $name) use ($key, $label) {
                    return strcasecmp($name, $label) === 0
                        || strcasecmp($name, $key) === 0
                        || strcasecmp('Grupo '.$name, $key) === 0;
                });

                return $index === false ? 999 : $index;
            });
        }

        return $groups->sortBy(function ($rows, $key) {
            if ($key === 'Sin grupo') {
                return 'zzz';
            }

            return mb_strtoupper((string) $key);
        }, SORT_NATURAL);
    }

    /**
     * @return Collection<string, Collection<int, FixtureMatch>>
     */
    public function brackets(Category $category): Collection
    {
        return FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'penaltyKicks'])
            ->where('category_id', $category->id)
            ->orderBy('scheduled_at')
            ->get()
            ->filter(fn (FixtureMatch $match) => $this->isKnockout($match))
            ->groupBy(fn (FixtureMatch $match) => $match->stage ?: 'Eliminatorias')
            ->sortKeysUsing(fn (string $a, string $b) => $this->knockoutOrder($a) <=> $this->knockoutOrder($b));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fairPlay(Category $category): Collection
    {
        $category->loadMissing(['teams.delegation']);
        $events = $this->closedEvents($category);
        $incidents = $this->closedIncidents($category);
        $playerWeights = $category->fairPlayWeights();

        return $category->teams
            ->map(function (Team $team) use ($events, $incidents, $playerWeights, $category) {
                $yellows = $events->where('type', 'yellow')->where('team_id', $team->id)->count();
                $reds = $events->where('type', 'red')->where('team_id', $team->id)->count();
                $staffYellows = $events->where('type', 'staff_yellow')->where('team_id', $team->id)->count();
                $staffReds = $events->where('type', 'staff_red')->where('team_id', $team->id)->count();
                $teamIncidents = $incidents->filter(function (MatchSheetIncident $incident) use ($team) {
                    if (! FairPlayRules::incidentIsDiscipline($incident)) {
                        return false;
                    }

                    return FairPlayRules::incidentAppliesToTeam($incident, (int) $team->id, (string) $team->name);
                })->unique('id');

                $counts = FairPlayRules::countIncidentKinds($teamIncidents);
                $counts['staff_yellow'] += $staffYellows;
                $counts['staff_expulsion'] += $staffReds;
                $incidentPoints = FairPlayRules::incidentPoints($counts, $category);
                $points = ($yellows * $playerWeights['yellow'])
                    + ($reds * $playerWeights['red'])
                    + $incidentPoints;

                return [
                    'team' => $team,
                    'yellows' => $yellows,
                    'reds' => $reds,
                    'staff_yellow' => $counts['staff_yellow'],
                    'staff_expulsion' => $counts['staff_expulsion'],
                    'family_misconduct' => $counts['family_misconduct'],
                    'family_expulsion' => $counts['family_expulsion'],
                    'adult_serious' => $counts['adult_serious'],
                    'late_arrival' => $counts['late_arrival'],
                    'incidents' => $teamIncidents->count(),
                    'adult_incidents' => $counts['staff_yellow']
                        + $counts['staff_expulsion']
                        + $counts['family_misconduct']
                        + $counts['family_expulsion']
                        + $counts['adult_serious'],
                    'points' => $points,
                ];
            })
            ->sort(FairPlayRules::compareRows(...))
            ->values()
            ->map(function (array $row, int $index) {
                $row['position'] = $index + 1;

                return $row;
            });
    }

    /**
     * @return array<string, Collection<int, array{0: string, 1: string, 2: int, 3: string, 4: ?Player, 5: ?Team, 6: int}>>
     */
    public function playerRankings(Category $category): array
    {
        return [
            'Goles' => $this->rankingList($category, 'goal', 12),
            'Asistencias' => $this->rankingList($category, 'assist', 12),
            'Tarjetas amarillas' => $this->rankingList($category, 'yellow', 12),
            'Tarjetas rojas' => $this->rankingList($category, 'red', 12),
        ];
    }

    /**
     * @return Collection<int, array{0: string, 1: string, 2: int, 3: string, 4: ?Player, 5: ?Team, 6: int}>
     */
    public function rankingList(Category $category, string $type, int $limit = 8): Collection
    {
        $rows = [];

        foreach ($this->closedEvents($category)->where('type', $type) as $event) {
            $player = $event->player?->fullName() ?: 'Sin jugador';
            $team = $event->team?->name ?: $event->player?->team?->name ?: 'Sin equipo';
            $key = $event->player_id ? 'p-'.$event->player_id : 'n-'.$player.'-'.$team;

            if (! isset($rows[$key])) {
                $rows[$key] = [
                    $player,
                    $team,
                    0,
                    $event->actorPhotoUrl(),
                    $event->player,
                    $event->team ?: $event->player?->team,
                    [],
                ];
            }

            $rows[$key][2]++;
            $matchId = (int) ($event->sheet?->match_id ?? 0);
            if ($matchId > 0) {
                $rows[$key][6][$matchId] = true;
            }
        }

        return collect($rows)
            ->sortByDesc(fn (array $row) => $row[2])
            ->map(function (array $row) {
                $row[6] = is_array($row[6] ?? null) ? count($row[6]) : 0;

                return $row;
            })
            ->values()
            ->take($limit);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function teamStatistics(Category $category): Collection
    {
        $category->loadMissing(['teams.delegation']);
        $rows = [];

        foreach ($category->teams as $team) {
            $rows[$team->id] = [
                'team' => $team,
                'played' => 0,
                'gf' => 0,
                'ga' => 0,
            ];
        }

        foreach ($this->countedMatches($category) as $match) {
            if (! isset($rows[$match->home_team_id], $rows[$match->away_team_id])) {
                continue;
            }

            [$homeScore, $awayScore] = $match->officialScores();
            $homeScore = (int) ($homeScore ?? 0);
            $awayScore = (int) ($awayScore ?? 0);
            $rows[$match->home_team_id]['played']++;
            $rows[$match->away_team_id]['played']++;
            $rows[$match->home_team_id]['gf'] += $homeScore;
            $rows[$match->home_team_id]['ga'] += $awayScore;
            $rows[$match->away_team_id]['gf'] += $awayScore;
            $rows[$match->away_team_id]['ga'] += $homeScore;
        }

        return collect($rows)
            ->map(function (array $row) {
                $row['gd'] = $row['gf'] - $row['ga'];

                return $row;
            })
            ->sortByDesc('gf')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function leastGoalsAgainst(Category $category): Collection
    {
        return $this->teamStatistics($category)
            ->filter(fn (array $row) => $row['played'] > 0)
            ->sortBy([
                ['ga', 'asc'],
                ['played', 'desc'],
                ['gd', 'desc'],
            ])
            ->values()
            ->map(function (array $row, int $index) {
                $row['position'] = $index + 1;

                return $row;
            });
    }

    public function goalsIn(Collection $matches): int
    {
        return (int) $matches->sum(fn (FixtureMatch $match) => (int) $match->home_score + (int) $match->away_score);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyMatch(array &$rows, FixtureMatch $match, Category $category): void
    {
        $homeId = $match->home_team_id;
        $awayId = $match->away_team_id;

        if (! isset($rows[$homeId], $rows[$awayId])) {
            return;
        }

        [$homeScore, $awayScore] = $match->officialScores();
        $homeScore = (int) ($homeScore ?? 0);
        $awayScore = (int) ($awayScore ?? 0);

        $rows[$homeId]['played']++;
        $rows[$awayId]['played']++;
        $rows[$homeId]['gf'] += $homeScore;
        $rows[$homeId]['ga'] += $awayScore;
        $rows[$awayId]['gf'] += $awayScore;
        $rows[$awayId]['ga'] += $homeScore;

        if ($homeScore > $awayScore) {
            $rows[$homeId]['won']++;
            $rows[$awayId]['lost']++;
            $rows[$homeId]['points'] += (int) $category->points_win;
            $rows[$awayId]['points'] += (int) $category->points_loss;
        } elseif ($homeScore < $awayScore) {
            $rows[$awayId]['won']++;
            $rows[$homeId]['lost']++;
            $rows[$awayId]['points'] += (int) $category->points_win;
            $rows[$homeId]['points'] += (int) $category->points_loss;
        } else {
            $rows[$homeId]['drawn']++;
            $rows[$awayId]['drawn']++;
            $rows[$homeId]['points'] += (int) $category->points_draw;
            $rows[$awayId]['points'] += (int) $category->points_draw;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStanding(Team $team): array
    {
        return [
            'team' => $team,
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'gf' => 0,
            'ga' => 0,
            'gd' => 0,
            'points' => 0,
            'percent' => 0,
            'pending' => 0,
            'fair_play' => 0,
            'sanctions' => 0,
        ];
    }

    private function groupLabel(Team $team): string
    {
        $group = trim((string) $team->group_name);

        return $group !== '' ? 'Grupo '.$group : 'Sin grupo';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $group
     * @return Collection<int, array<string, mixed>>
     */
    private function sortStandings(Collection $group, Category $category): Collection
    {
        $criteria = collect($category->tiebreakers ?? [])
            ->map(fn ($item) => mb_strtolower(trim((string) $item)))
            ->filter()
            ->values();

        return $group->sort(function (array $a, array $b) use ($criteria) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            foreach ($criteria as $rule) {
                $cmp = match (true) {
                    str_contains($rule, 'diferencia') => $b['gd'] <=> $a['gd'],
                    str_contains($rule, 'goles a favor') || $rule === 'gf' => $b['gf'] <=> $a['gf'],
                    str_contains($rule, 'fair') => $a['fair_play'] <=> $b['fair_play'],
                    default => 0,
                };

                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            if ($a['gd'] !== $b['gd']) {
                return $b['gd'] <=> $a['gd'];
            }

            if ($a['gf'] !== $b['gf']) {
                return $b['gf'] <=> $a['gf'];
            }

            return strcasecmp($a['team']->name, $b['team']->name);
        })->values();
    }

    /**
     * @return Collection<int, FixtureMatch>
     */
    public function countedMatches(Category $category): Collection
    {
        return FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'sheet'])
            ->where('category_id', $category->id)
            ->where('status', '!=', 'suspended')
            ->orderBy('scheduled_at')
            ->get()
            ->filter(fn (FixtureMatch $match) => $match->countsForStandings())
            ->values();
    }

    private function knockoutOrder(string $stage): int
    {
        $stage = mb_strtolower($stage);

        return match (true) {
            str_contains($stage, 'octavos') => 1,
            str_contains($stage, 'cuartos') => 2,
            str_contains($stage, 'semi') => 3,
            str_contains($stage, 'bronce') => 4,
            str_contains($stage, 'final') => 5,
            default => 6,
        };
    }

    /**
     * @return Collection<int, MatchSheetEvent>
     */
    private function closedEvents(Category $category): Collection
    {
        return MatchSheetEvent::query()
            ->with(['player.documents', 'player.team', 'team.delegation', 'sheet.match'])
            ->whereHas('sheet.match', function ($query) use ($category) {
                $query
                    ->where('category_id', $category->id)
                    ->whereIn('status', $this->closedStatuses());
            })
            ->get();
    }

    /**
     * @return Collection<int, MatchSheetIncident>
     */
    private function closedIncidents(Category $category): Collection
    {
        return MatchSheetIncident::query()
            ->with('sheet')
            ->whereHas('sheet.match', function ($query) use ($category) {
                $query
                    ->where('category_id', $category->id)
                    ->whereIn('status', $this->closedStatuses());
            })
            ->get();
    }
}
