<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Guardian;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Models\Tournament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Llena categorías, equipos y jugadores del torneo demo principal.
 * Idempotente: se puede ejecutar varias veces sin duplicar datos.
 *
 *   php artisan db:seed --class=DemoCompetitionSeeder
 */
class DemoCompetitionSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (app()->environment('production') && ! filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $competition = json_decode(
            File::get(database_path('data/demo-competition.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $playersData = json_decode(
            File::get(database_path('data/demo-players.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $teamsPerCategory = (int) ($competition['teams_per_category'] ?? 8);
        $targetPlayers = (int) ($competition['players_per_team'] ?? 14);
        $teamPool = $competition['team_pool'];
        $statusByJersey = $competition['player_status_by_jersey'] ?? [];

        $tournamentSlugs = array_values(array_unique(array_filter([
            $competition['tournament_slug'] ?? null,
            ...($competition['extra_tournament_slugs'] ?? []),
        ])));

        $teamsCreated = 0;
        $playersCreated = 0;
        $categoryCount = 0;

        foreach ($tournamentSlugs as $slug) {
            $tournament = Tournament::query()->where('slug', $slug)->first();
            if (! $tournament) {
                continue;
            }

            $categories = Category::query()
                ->where('tournament_id', $tournament->id)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($categories->isEmpty()) {
                continue;
            }

            $categoryCount += $categories->count();

            foreach ($categories as $category) {
            $existingTeams = $category->teams()->orderBy('id')->get();
            $neededTeams = max(0, $teamsPerCategory - $existingTeams->count());

            foreach (array_slice($teamPool, 0, $neededTeams) as $index => $row) {
                $suffix = $categories->count() > 1 && $existingTeams->isNotEmpty()
                    ? ' '.Str::slug($category->name, ' ')
                    : '';

                $teamName = trim($row['name'].$suffix);
                if ($category->teams()->where('name', $teamName)->exists()) {
                    continue;
                }

                Team::create([
                    'tournament_id' => $tournament->id,
                    'category_id' => $category->id,
                    'name' => $teamName,
                    'delegation_name' => $row['delegation'],
                    'city' => $row['city'],
                    'country_code' => $row['country_code'],
                    'group_name' => $row['group'],
                    'home_kit' => 'Azul / Rojo',
                    'away_kit' => 'Blanca',
                    'player_capacity' => $category->max_players ?: 22,
                    'shield_path' => 'images/stc-logo.png',
                    'status' => 'approved',
                    'roster_open' => true,
                ]);
                $teamsCreated++;
            }

            $category->load(['teams.players']);

            foreach ($category->teams as $team) {
                $added = $this->fillTeamPlayers(
                    $team,
                    $category,
                    $targetPlayers,
                    $playersData,
                    $statusByJersey
                );
                $playersCreated += $added;
                $this->ensureTeamStaff($team, $competition['staff_roles'] ?? []);
            }
            }
        }

        if ($categoryCount === 0) {
            $this->command?->warn('No hay categorías activas. Ejecutá DatabaseSeeder primero.');

            return;
        }

        $this->command?->info(sprintf(
            'DemoCompetitionSeeder: %d categorías, %d equipos nuevos, %d jugadores nuevos.',
            $categoryCount,
            $teamsCreated,
            $playersCreated
        ));
    }

    /**
     * @param  array<string, mixed>  $playersData
     * @param  array<string, string>  $statusByJersey
     */
    private function fillTeamPlayers(
        Team $team,
        Category $category,
        int $target,
        array $playersData,
        array $statusByJersey
    ): int {
        $existingCount = $team->players()->count();
        if ($existingCount >= $target) {
            $this->ensurePlayerDocuments($team);

            return 0;
        }

        $positions = $playersData['positions'];
        $lastNames = $playersData['last_names'];
        $female = str_contains(mb_strtolower($category->branch.' '.$category->name), 'femen');
        $firstNames = $female ? $playersData['first_names_femenino'] : $playersData['first_names'];
        $birthYear = (int) preg_replace('/\D.*$/', '', (string) ($category->birth_year ?: '2014')) ?: 2014;

        $usedJerseys = $team->players()->pluck('jersey_number')->filter()->map(fn ($n) => (int) $n)->all();
        $usedNames = $team->players()
            ->get()
            ->map(fn (Player $player) => mb_strtolower($player->first_name.'|'.$player->last_name))
            ->all();

        $created = 0;
        $nameIndex = ($team->id * 17) + ($category->id * 3);

        for ($slot = $existingCount; $slot < $target; $slot++) {
            $jersey = $this->nextJersey($usedJerseys);
            $usedJerseys[] = $jersey;

            [$firstName, $lastName] = $this->nextName($firstNames, $lastNames, $nameIndex, $usedNames);
            $usedNames[] = mb_strtolower($firstName.'|'.$lastName);
            $nameIndex++;

            $status = $statusByJersey[(string) $jersey] ?? 'enabled';

            $player = Player::updateOrCreate(
                [
                    'team_id' => $team->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ],
                [
                    'document_number' => (string) (50000000 + ($team->id * 20) + $jersey),
                    'birth_date' => sprintf('%d-%02d-%02d', $birthYear, ($jersey % 12) + 1, min(28, ($jersey * 2) + 1)),
                    'nationality' => $team->country_code === 'BR' ? 'Brasil' : 'Argentina',
                    'position' => $positions[($jersey - 1) % count($positions)],
                    'jersey_number' => $jersey,
                    'preferred_foot' => $jersey % 2 === 0 ? 'Zurda' : 'Derecha',
                    'photo_path' => 'images/stc-logo.png',
                    'status' => $status,
                    'observation_reason' => $status === 'observed' ? 'Falta renovar apto médico.' : null,
                    'notes' => $status === 'rejected' ? 'Documento ilegible. Pedir nueva foto del DNI.' : null,
                ]
            );

            Guardian::updateOrCreate(
                ['player_id' => $player->id],
                [
                    'name' => 'Familiar de '.$lastName,
                    'relationship' => 'Padre',
                    'email' => Str::slug($lastName.'.'.$firstName, '.').'.tutor@club.demo',
                    'phone' => '+54 11 5555-'.str_pad((string) (($team->id + $jersey) % 9000 + 1000), 4, '0', STR_PAD_LEFT),
                    'consent_status' => in_array($status, ['enabled', 'approved'], true) ? 'approved' : 'pending',
                ]
            );

            $this->syncDocuments($player, $status);
            $created++;
        }

        return $created;
    }

    private function ensurePlayerDocuments(Team $team): void
    {
        foreach ($team->players as $player) {
            $this->syncDocuments($player, $player->status);
        }
    }

    private function syncDocuments(Player $player, string $status): void
    {
        $approved = in_array($status, ['enabled', 'approved'], true);

        foreach (Player::documentTypes() as $type) {
            $documentStatus = match (true) {
                $approved => 'approved',
                $status === 'observed' && ! in_array($type, ['DNI', 'DNI frente'], true) => 'observed',
                $status === 'rejected' && $type === 'Uso de imagen' => 'rejected',
                $status === 'pending' && in_array($type, ['Apto médico', 'Autorización'], true) => 'pending',
                default => 'pending',
            };

            PlayerDocument::updateOrCreate(
                ['player_id' => $player->id, 'type' => $type],
                ['status' => $documentStatus]
            );
        }
    }

    /**
     * @param  list<array{role: string, first_name: string, last_name: string}>  $roles
     */
    private function ensureTeamStaff(Team $team, array $roles): void
    {
        foreach ($roles as $index => $row) {
            TeamStaff::updateOrCreate(
                ['team_id' => $team->id, 'role' => $row['role']],
                [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'document_number' => (string) (30000000 + ($team->id * 10) + $index),
                    'photo_path' => 'images/stc-logo.png',
                    'status' => 'active',
                ]
            );
        }
    }

    /**
     * @param  list<int>  $usedJerseys
     */
    private function nextJersey(array $usedJerseys): int
    {
        for ($jersey = 1; $jersey <= 22; $jersey++) {
            if (! in_array($jersey, $usedJerseys, true)) {
                return $jersey;
            }
        }

        return count($usedJerseys) + 1;
    }

    /**
     * @param  list<string>  $firstNames
     * @param  list<string>  $lastNames
     * @param  list<string>  $usedNames
     * @return array{0: string, 1: string}
     */
    private function nextName(array $firstNames, array $lastNames, int $index, array $usedNames): array
    {
        for ($offset = 0; $offset < 400; $offset++) {
            $firstName = $firstNames[($index + $offset) % count($firstNames)];
            $lastName = $lastNames[(($index * 3) + $offset) % count($lastNames)];
            $key = mb_strtolower($firstName.'|'.$lastName);

            if (! in_array($key, $usedNames, true)) {
                return [$firstName, $lastName];
            }
        }

        return ['Jugador', 'Demo '.$index];
    }
}
