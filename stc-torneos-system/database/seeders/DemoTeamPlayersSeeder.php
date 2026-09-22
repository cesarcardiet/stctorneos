<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DemoTeamPlayersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $data = json_decode(File::get(database_path('data/demo-players.json')), true, 512, JSON_THROW_ON_ERROR);
        $target = (int) ($data['target_per_team'] ?? 12);
        $positions = $data['positions'];
        $lastNames = $data['last_names'];

        $teams = Team::query()->with(['category', 'players'])->orderBy('id')->get();

        foreach ($teams as $team) {
            $existingCount = $team->players->count();
            if ($existingCount >= $target) {
                continue;
            }

            $usedJerseys = $team->players->pluck('jersey_number')->filter()->map(fn ($number) => (int) $number)->all();
            $usedNames = $team->players
                ->map(fn (Player $player) => mb_strtolower($player->first_name.'|'.$player->last_name))
                ->all();

            $female = str_contains(mb_strtolower($team->category?->branch.' '.$team->category?->name), 'femen');
            $firstNames = $female ? $data['first_names_femenino'] : $data['first_names'];
            $birthYear = (int) preg_replace('/\D.*$/', '', (string) ($team->category?->birth_year ?: '2014')) ?: 2014;
            $nameIndex = $team->id * 17;

            for ($slot = $existingCount; $slot < $target; $slot++) {
                $jersey = $this->nextJersey($usedJerseys);
                $usedJerseys[] = $jersey;

                [$firstName, $lastName] = $this->nextName($firstNames, $lastNames, $nameIndex, $usedNames);
                $usedNames[] = mb_strtolower($firstName.'|'.$lastName);
                $nameIndex++;

                $status = match ($jersey) {
                    10 => 'pending',
                    11 => 'observed',
                    12 => 'approved',
                    default => 'enabled',
                };

                $player = Player::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                    ],
                    [
                        'document_number' => (string) (50000000 + ($team->id * 20) + $jersey),
                        'birth_date' => sprintf('%d-%02d-%02d', $birthYear, ($jersey % 12) + 1, min(28, ($jersey * 2) + 1)),
                        'nationality' => 'Argentina',
                        'position' => $positions[($jersey - 1) % count($positions)],
                        'jersey_number' => $jersey,
                        'preferred_foot' => $jersey % 2 === 0 ? 'Zurda' : 'Derecha',
                        'photo_path' => 'images/stc-logo.png',
                        'status' => $status,
                        'observation_reason' => $status === 'observed' ? 'Falta renovar apto médico.' : null,
                    ]
                );

                Guardian::updateOrCreate(
                    ['player_id' => $player->id],
                    [
                        'name' => 'Familiar de '.$lastName,
                        'relationship' => 'Padre',
                        'email' => Str::slug($lastName.'.'.$firstName, '.').'.tutor@club.demo',
                        'phone' => '+54 11 5555-'.str_pad((string) (($team->id + $jersey) % 9000 + 1000), 4, '0', STR_PAD_LEFT),
                        'consent_status' => $status === 'enabled' || $status === 'approved' ? 'approved' : 'pending',
                    ]
                );

                $approved = in_array($status, ['enabled', 'approved'], true);
                foreach (Player::documentTypes() as $type) {
                    $documentStatus = 'pending';
                    if ($approved) {
                        $documentStatus = 'approved';
                    } elseif ($status === 'observed' && $type !== 'DNI frente') {
                        $documentStatus = 'observed';
                    }

                    PlayerDocument::updateOrCreate(
                        ['player_id' => $player->id, 'type' => $type],
                        ['status' => $documentStatus]
                    );
                }
            }
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
