<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FixtureGenerator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{created: int, idle: list<string>}
     */
    public function generate(Category $category, User $user, array $data): array
    {
        $category->loadMissing('teams');
        $fields = $this->fieldsFor($category, $user);
        if ($fields->isEmpty()) {
            return ['created' => 0, 'idle' => []];
        }

        $teams = $category->teams->whereIn('status', ['approved', 'enabled', 'pending'])->values();
        if ($teams->count() < 2) {
            return ['created' => 0, 'idle' => []];
        }

        $scope = (string) ($data['scope'] ?? 'group');
        $legs = (string) ($data['legs'] ?? 'ida');
        $stage = trim((string) ($data['stage'] ?? '')) ?: '1º Fase';
        $fillByes = array_key_exists('fill_byes', $data) ? (bool) $data['fill_byes'] : true;
        $mixPairs = $this->normalizeMixPairs($data['mix_pairs'] ?? [], $category);
        $pairings = $scope === 'intergroup'
            ? $this->intergroupPairings($teams, $mixPairs)
            : $this->groupPairings($teams);

        if ($pairings === []) {
            return ['created' => 0, 'idle' => []];
        }

        $rounds = $this->prepareRounds($this->packRounds($pairings), $teams, $mixPairs, $fillByes && $scope === 'group');
        $idle = $this->idleTeamLabels($teams, $rounds[0] ?? []);

        if ($legs === 'ida_vuelta') {
            $returns = [];
            foreach ($pairings as $pair) {
                $returns[] = [$pair[1], $pair[0]];
            }
            $returnRounds = $this->prepareRounds($this->packRounds($returns), $teams, $mixPairs, $fillByes && $scope === 'group');
            $rounds = array_merge($rounds, $returnRounds);
        }
        $duration = max(40, ((int) ($category->periods ?: 2)) * ((int) ($category->period_duration ?: 25)));
        $gap = (int) ($data['gap_minutes'] ?? 15);
        $slot = Carbon::parse($data['start_at']);
        $published = (bool) ($data['published'] ?? false);
        $fieldFreeAt = [];
        $created = 0;
        $fecha = 1;

        foreach ($rounds as $roundPairs) {
            $roundName = 'Fecha '.$fecha;
            foreach ($roundPairs as [$home, $away]) {
                if ($this->pairingExists($category, $home, $away, $legs === 'ida_vuelta')) {
                    continue;
                }

                $field = $this->nextFreeField($fields, $slot, $duration, $gap, $fieldFreeAt);
                FixtureMatch::create([
                    'tournament_id' => $category->tournament_id,
                    'category_id' => $category->id,
                    'field_id' => $field->id,
                    'home_team_id' => $home->id,
                    'away_team_id' => $away->id,
                    'scheduled_at' => $slot->copy(),
                    'stage' => $stage,
                    'round' => $roundName,
                    'status' => 'scheduled',
                    'duration_minutes' => $duration,
                    'published' => $published,
                    'published_at' => $published ? now() : null,
                ]);

                $fieldFreeAt[$field->id] = $slot->copy()->addMinutes($duration + $gap);
                $created++;

                if (($data['priority'] ?? '') === 'compact') {
                    $slot->addMinutes($gap);
                } else {
                    $slot->addMinutes($duration + $gap);
                }
            }
            $fecha++;
        }

        return ['created' => $created, 'idle' => $idle];
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return list<array{0: Team, 1: Team}>
     */
    private function groupPairings(Collection $teams): array
    {
        $pairings = [];
        foreach ($this->teamsByGroup($teams) as $groupTeams) {
            $pairings = array_merge($pairings, $this->roundRobinPairs($this->sortTeams($groupTeams)));
        }

        return $pairings;
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  array<string, string>  $mixPairs
     * @return list<array{0: Team, 1: Team}>
     */
    private function intergroupPairings(Collection $teams, array $mixPairs): array
    {
        $groups = $this->teamsByGroup($teams);
        if ($groups->count() < 2) {
            return [];
        }

        $pairings = [];
        $seen = [];
        foreach ($mixPairs as $homeKey => $awayKey) {
            $token = collect([$homeKey, $awayKey])->sort()->implode('-');
            if (isset($seen[$token]) || $homeKey === $awayKey) {
                continue;
            }
            $seen[$token] = true;
            foreach ($this->sortTeams($groups->get($homeKey, collect())) as $home) {
                foreach ($this->sortTeams($groups->get($awayKey, collect())) as $away) {
                    $pairings[] = [$home, $away];
                }
            }
        }

        return $pairings;
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return list<array{0: Team, 1: Team}>
     */
    private function roundRobinPairs(Collection $teams): array
    {
        $list = $teams->values();
        if ($list->count() < 2) {
            return [];
        }
        if ($list->count() % 2 === 1) {
            $list->push(null);
        }

        $pairings = [];
        $n = $list->count();
        $rounds = $n - 1;
        $half = (int) ($n / 2);

        for ($round = 0; $round < $rounds; $round++) {
            for ($i = 0; $i < $half; $i++) {
                $home = $list[$i];
                $away = $list[$n - 1 - $i];
                if ($home && $away) {
                    $pairings[] = [$home, $away];
                }
            }

            $fixed = $list->shift();
            $moved = $list->pop();
            $list = collect([$fixed, $moved])->concat($list)->values();
        }

        return $pairings;
    }

    /**
     * @param  list<array{0: Team, 1: Team}>  $pairings
     * @return list<list<array{0: Team, 1: Team}>>
     */
    private function packRounds(array $pairings): array
    {
        $remaining = $pairings;
        $rounds = [];

        while ($remaining !== []) {
            $used = [];
            $round = [];
            $next = [];

            foreach ($remaining as $pair) {
                [$home, $away] = $pair;
                if (! isset($used[$home->id]) && ! isset($used[$away->id])) {
                    $round[] = $pair;
                    $used[$home->id] = true;
                    $used[$away->id] = true;
                } else {
                    $next[] = $pair;
                }
            }

            if ($round === []) {
                break;
            }

            $rounds[] = $round;
            $remaining = $next;
        }

        return $rounds;
    }

    /**
     * @param  list<list<array{0: Team, 1: Team}>>  $rounds
     * @param  Collection<int, Team>  $teams
     * @param  array<string, string>  $mixPairs
     * @return list<list<array{0: Team, 1: Team}>>
     */
    private function prepareRounds(array $rounds, Collection $teams, array $mixPairs, bool $fillByes): array
    {
        foreach ($rounds as $index => $round) {
            if ($fillByes) {
                $round = $this->fillRoundWithInterzonals($round, $teams, $mixPairs);
            }
            $rounds[$index] = $this->sortRoundPairs($round);
        }

        return $rounds;
    }

    /**
     * Completa cada fecha: el libre de C juega con el libre de D, E con F, etc.
     *
     * @param  list<array{0: Team, 1: Team}>  $round
     * @param  Collection<int, Team>  $teams
     * @param  array<string, string>  $mixPairs
     * @return list<array{0: Team, 1: Team}>
     */
    private function fillRoundWithInterzonals(array $round, Collection $teams, array $mixPairs): array
    {
        $playing = [];
        foreach ($round as [$home, $away]) {
            $playing[$home->id] = true;
            $playing[$away->id] = true;
        }

        $grouped = $this->teamsByGroup($teams);
        $idle = $teams
            ->filter(fn (Team $team) => ! isset($playing[$team->id]))
            ->groupBy(fn (Team $team) => $this->groupKey($team))
            ->map(fn (Collection $group) => $this->sortTeams($group));

        $seen = [];
        foreach ($mixPairs as $homeKey => $awayKey) {
            $token = collect([$homeKey, $awayKey])->sort()->implode('-');
            if (isset($seen[$token]) || $homeKey === $awayKey) {
                continue;
            }
            $seen[$token] = true;

            $homeIdle = $idle->get($homeKey, collect());
            $awayIdle = $idle->get($awayKey, collect());
            $homeGroup = $grouped->get($homeKey, collect());
            $awayGroup = $grouped->get($awayKey, collect());
            if ($homeIdle->isEmpty() || $awayIdle->isEmpty()) {
                continue;
            }
            if ($homeIdle->count() >= $homeGroup->count() || $awayIdle->count() >= $awayGroup->count()) {
                continue;
            }
            while ($homeIdle->isNotEmpty() && $awayIdle->isNotEmpty()) {
                $round[] = [$homeIdle->shift(), $awayIdle->shift()];
            }
        }

        return $round;
    }

    /**
     * @param  list<array{0: Team, 1: Team}>  $round
     * @return list<array{0: Team, 1: Team}>
     */
    private function sortRoundPairs(array $round): array
    {
        usort($round, fn (array $left, array $right) => strnatcasecmp(
            $this->pairSortKey($left),
            $this->pairSortKey($right)
        ));

        return $round;
    }

    /**
     * @param  array{0: Team, 1: Team}  $pair
     */
    private function pairSortKey(array $pair): string
    {
        $names = [
            trim((string) $pair[0]->name),
            trim((string) $pair[1]->name),
        ];
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names[0].' '.$names[1];
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return Collection<string, Collection<int, Team>>
     */
    private function teamsByGroup(Collection $teams): Collection
    {
        return $teams
            ->groupBy(fn (Team $team) => $this->groupKey($team))
            ->sortKeysUsing('strnatcasecmp');
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return Collection<int, Team>
     */
    private function sortTeams(Collection $teams): Collection
    {
        return $teams->sortBy(fn (Team $team) => $team->name, SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    private function normalizeMixPairs(array $raw, Category $category): array
    {
        $pairs = [];
        foreach ($raw as $from => $to) {
            $from = strtoupper(trim((string) $from));
            $to = strtoupper(trim((string) $to));
            if ($from === '' || $to === '' || $from === $to) {
                continue;
            }
            if (isset($pairs[$to]) && $pairs[$to] !== $from) {
                unset($pairs[$pairs[$to]]);
            }
            if (isset($pairs[$from]) && $pairs[$from] !== $to) {
                unset($pairs[$pairs[$from]]);
            }
            $pairs[$from] = $to;
            $pairs[$to] = $from;
        }

        return $pairs !== [] ? $pairs : $category->defaultMixPairs();
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  list<array{0: Team, 1: Team}>  $round
     * @return list<string>
     */
    private function idleTeamLabels(Collection $teams, array $round): array
    {
        $playing = [];
        foreach ($round as [$home, $away]) {
            $playing[$home->id] = true;
            $playing[$away->id] = true;
        }

        return $teams
            ->filter(fn (Team $team) => ! isset($playing[$team->id]))
            ->map(function (Team $team) {
                $group = $this->groupKey($team);

                return $team->name.' (Grupo '.$group.')';
            })
            ->values()
            ->all();
    }

    private function groupKey(Team $team): string
    {
        $group = strtoupper(trim((string) $team->group_name));

        return $group !== '' ? $group : 'A';
    }

    private function pairingExists(Category $category, Team $home, Team $away, bool $exact): bool
    {
        $query = FixtureMatch::query()->where('category_id', $category->id);

        if ($exact) {
            return $query->where('home_team_id', $home->id)->where('away_team_id', $away->id)->exists();
        }

        return $query->where(function ($query) use ($home, $away) {
            $query->where(function ($query) use ($home, $away) {
                $query->where('home_team_id', $home->id)->where('away_team_id', $away->id);
            })->orWhere(function ($query) use ($home, $away) {
                $query->where('home_team_id', $away->id)->where('away_team_id', $home->id);
            });
        })->exists();
    }

    /**
     * @return Collection<int, Field>
     */
    private function fieldsFor(Category $category, User $user): Collection
    {
        $fields = Field::query()
            ->accessibleTo($user)
            ->where('status', 'available')
            ->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))
            ->orderBy('name')
            ->get();

        if ($fields->isEmpty()) {
            $fields = Field::query()
                ->accessibleTo($user)
                ->where('status', 'available')
                ->orderBy('name')
                ->get();
        }

        return $fields;
    }

    /**
     * @param  Collection<int, Field>  $fields
     * @param  array<int, Carbon>  $fieldFreeAt
     */
    private function nextFreeField(Collection $fields, Carbon $slot, int $duration, int $gap, array $fieldFreeAt): Field
    {
        foreach ($fields as $field) {
            $freeAt = $fieldFreeAt[$field->id] ?? $slot->copy()->subDay();
            if ($slot->gte($freeAt)) {
                return $field;
            }
        }

        return $fields->first();
    }
}
