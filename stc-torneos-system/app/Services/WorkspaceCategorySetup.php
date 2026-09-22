<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\Venue;
use App\Support\CategoryWorkspace;
use Carbon\Carbon;

class WorkspaceCategorySetup
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Tournament $tournament, array $data): Category
    {
        $competitionFormat = Category::resolveCompetitionFormat(
            $data['competition_format'] ?? null,
            $data['custom_competition_format'] ?? null,
        );
        $needsGroups = Category::formatNeedsGroups((string) ($data['competition_format'] ?? ''));
        $groupsCount = $needsGroups
            ? max(1, min(Category::MAX_GROUPS, (int) ($data['groups_count'] ?? 2)))
            : 0;
        $modality = (string) $data['modality'];

        $category = Category::create([
            'tournament_id' => $tournament->id,
            'name' => $data['name'],
            'birth_year' => $data['birth_year'],
            'branch' => $data['branch'],
            'modality' => $modality,
            'format' => $modality,
            'status' => 'active',
            'team_limit' => 24,
            'min_players' => 8,
            'max_players' => 22,
            'players_on_field' => str_contains($modality, '5') ? 5 : (str_contains($modality, '7') ? 7 : 11),
            'substitutes' => 7,
            'periods' => 2,
            'period_duration' => 25,
            'competition_format' => $competitionFormat,
            'groups_count' => $groupsCount,
            'teams_per_group' => $needsGroups ? max(1, (int) ($data['teams_per_group'] ?? 4)) : 0,
            'qualifiers_count' => 4,
            'points_win' => (int) ($data['points_win'] ?? 3),
            'points_draw' => (int) ($data['points_draw'] ?? 1),
            'points_loss' => (int) ($data['points_loss'] ?? 0),
            'image_path' => Category::inheritedBannerPath(
                (string) $data['name'],
                $data['birth_year'] ?? null,
                $data['branch'] ?? null,
            ),
            'sort_order' => (int) Category::query()->where('tournament_id', $tournament->id)->max('sort_order') + 1,
            'rules' => $data['rules'] ?? null,
            'workspace_config' => array_replace_recursive(CategoryWorkspace::defaults(), [
                'description' => $data['description'] ?? '',
                'prizes' => [
                    'first' => $data['prize_first'] ?? 'Campeón',
                    'second' => $data['prize_second'] ?? 'Subcampeón',
                    'third' => $data['prize_third'] ?? 'Tercer puesto',
                    'other' => $data['prize_other'] ?? 'Fair Play',
                ],
            ]),
        ]);

        $teams = [];
        foreach ($data['teams'] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $team = Team::create([
                'tournament_id' => $tournament->id,
                'category_id' => $category->id,
                'name' => $name,
                'delegation_name' => $name,
                'group_name' => strtoupper(trim((string) ($row['group'] ?? 'A'))) ?: 'A',
                'player_capacity' => 22,
                'shield_path' => 'images/stc-logo.png',
                'status' => 'approved',
                'roster_open' => true,
            ]);

            $this->addPlayers($team, (string) ($row['players'] ?? ''));
            $teams[] = $team;
        }

        $this->scheduleGroupMatches($tournament, $category, $teams);

        return $category->refresh();
    }

    private function addPlayers(Team $team, string $raw): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $number = 1;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$lastName, $firstName] = $this->splitName($line);

            Player::create([
                'team_id' => $team->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'nationality' => 'Argentina',
                'jersey_number' => $number <= 99 ? $number : null,
                'status' => 'pending',
            ]);
            $number++;
        }
    }

    /**
     * @param  list<Team>  $teams
     */
    private function scheduleGroupMatches(Tournament $tournament, Category $category, array $teams): void
    {
        $field = $this->firstField($tournament);
        if (! $field || count($teams) < 2) {
            return;
        }

        $byGroup = collect($teams)->groupBy(fn (Team $team) => $team->group_name ?: 'A');
        $start = Carbon::parse($tournament->starts_at?->format('Y-m-d').' 09:00:00');
        $slot = 0;

        foreach ($byGroup as $group => $groupTeams) {
            $list = $groupTeams->values();
            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    FixtureMatch::create([
                        'tournament_id' => $tournament->id,
                        'category_id' => $category->id,
                        'field_id' => $field->id,
                        'home_team_id' => $list[$i]->id,
                        'away_team_id' => $list[$j]->id,
                        'scheduled_at' => $start->copy()->addMinutes($slot * 80),
                        'stage' => '1º Fase',
                        'round' => 'Fecha 1',
                        'status' => 'scheduled',
                        'duration_minutes' => 50,
                        'published' => false,
                    ]);
                    $slot++;
                }
            }
        }
    }

    private function firstField(Tournament $tournament): ?Field
    {
        $existing = Field::query()
            ->whereHas('venue', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $venue = Venue::create([
            'tournament_id' => $tournament->id,
            'name' => 'Sede principal',
            'city' => $tournament->city ?: 'Santa Teresita',
            'status' => 'active',
        ]);

        return Field::create([
            'venue_id' => $venue->id,
            'name' => 'CANCHA 1',
            'surface' => 'Césped sintético',
            'status' => 'available',
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', $name) ?: [];

        if (count($parts) <= 1) {
            return [$name, $name];
        }

        return [$parts[0], implode(' ', array_slice($parts, 1))];
    }

    /**
     * @return list<array{name: string, group: string, players: string}>
     */
    public static function demoTeams(): array
    {
        return [
            [
                'name' => 'Almirante Brown',
                'group' => 'B',
                'players' => "Pérez Juan\nGómez Lucas\nDíaz Mateo\nLópez Thiago\nFernández Benjamín\nRuiz Santiago",
            ],
            [
                'name' => 'CADU',
                'group' => 'A',
                'players' => "Martínez Thiago\nSosa Franco\nÁlvarez Nicolás\nRomero Lautaro\nVega Tomás\nCastro Ian",
            ],
            [
                'name' => 'Selectivo Las Flores',
                'group' => 'B',
                'players' => "Suárez Agustín\nMolina Bautista\nRojas Felipe\nNavarro Bruno\nPaz Valentino\nIbarra Liam",
            ],
            [
                'name' => 'Fútbol Club Santa Teresita',
                'group' => 'B',
                'players' => "Baiz Luca\nCejas Lautaro\nColunga Santiago\nCarrion Joaquin\nAyala Thiago\nOjeda Matias",
            ],
            [
                'name' => 'Tiki Taki - Venezuela',
                'group' => 'A',
                'players' => "Ramos Luis\nTorres Eliel\nHerrera Fabián\nGarcía Marco\nParra Aaron\nPardo Juan",
            ],
            [
                'name' => 'LIFFA - Uruguay',
                'group' => 'A',
                'players' => "Costa Agustin\nRojas David\nGonzalez Emiliano\nDelgado Marcos\nHernandez Matias\nGodoy Valentin",
            ],
        ];
    }
}
