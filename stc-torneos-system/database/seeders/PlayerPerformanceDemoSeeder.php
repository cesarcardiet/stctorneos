<?php

namespace Database\Seeders;

use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\MatchSheet;
use App\Models\MatchSheetEvent;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Carga historial y estadísticas demo para el jugador Lautaro Ruiz (Leones FC).
 */
class PlayerPerformanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $player = Player::query()
            ->where('first_name', 'Lautaro')
            ->where('last_name', 'Ruiz')
            ->first();

        $team = Team::query()->where('name', 'Leones FC')->with('category')->first();

        if (! $player || ! $team?->category) {
            $this->command?->warn('PlayerPerformanceDemoSeeder: no se encontró Lautaro Ruiz o Leones FC.');

            return;
        }

        $teams = Team::query()
            ->where('category_id', $team->category_id)
            ->get()
            ->keyBy('name');

        $field = Field::query()
            ->whereHas('venue', fn ($query) => $query->where('tournament_id', $team->tournament_id))
            ->first();

        if (! $field) {
            $this->command?->warn('PlayerPerformanceDemoSeeder: no hay canchas para el torneo.');

            return;
        }

        $this->seedFinishedMatch(
            categoryId: (int) $team->category_id,
            tournamentId: (int) $team->tournament_id,
            fieldId: (int) $field->id,
            home: $teams['Unión FC'],
            away: $teams['Leones FC'],
            scheduledAt: '2026-11-23 10:00:00',
            round: 'Fecha 3',
            homeScore: 1,
            awayScore: 2,
            player: $player,
            teamId: (int) $team->id,
            events: [
                [34, 'goal', 'Gol de cabeza'],
                [67, 'assist', 'Asistencia al segundo gol'],
            ]
        );

        $this->seedFinishedMatch(
            categoryId: (int) $team->category_id,
            tournamentId: (int) $team->tournament_id,
            fieldId: (int) $field->id,
            home: $teams['Leones FC'],
            away: $teams['Pampero'],
            scheduledAt: '2026-11-30 11:30:00',
            round: 'Fecha 4',
            homeScore: 3,
            awayScore: 1,
            player: $player,
            teamId: (int) $team->id,
            events: [
                [12, 'goal', 'Gol de zurda'],
                [41, 'goal', 'Doblete en el partido'],
                [55, 'yellow', 'Reclamo al árbitro'],
            ]
        );

        $this->seedFinishedMatch(
            categoryId: (int) $team->category_id,
            tournamentId: (int) $team->tournament_id,
            fieldId: (int) $field->id,
            home: $teams['San Lorenzo'],
            away: $teams['Leones FC'],
            scheduledAt: '2026-12-07 09:00:00',
            round: 'Fecha 5',
            homeScore: 2,
            awayScore: 2,
            player: $player,
            teamId: (int) $team->id,
            events: [
                [28, 'goal', 'Empate parcial'],
                [73, 'assist', 'Centro al empate'],
            ]
        );

        $liveMatch = FixtureMatch::query()
            ->where('home_team_id', $teams['Leones FC']->id)
            ->where('away_team_id', $teams['Unión FC']->id)
            ->first();

        if ($liveMatch) {
            $liveMatch->update([
                'status' => 'live',
                'home_score' => 1,
                'away_score' => 0,
                'minute' => '38:10',
                'published' => true,
                'published_at' => now(),
            ]);

            $sheet = MatchSheet::updateOrCreate(
                ['match_id' => $liveMatch->id],
                [
                    'status' => 'loading',
                    'validation_status' => 'pending',
                    'current_step' => 2,
                    'referee_name' => 'Carla Pérez',
                    'home_score' => 1,
                    'away_score' => 0,
                    'locked' => false,
                ]
            );

            $sheet->events()->where('player_id', $player->id)->delete();
            MatchSheetEvent::create([
                'match_sheet_id' => $sheet->id,
                'type' => 'goal',
                'minute' => 38,
                'player_id' => $player->id,
                'team_id' => $team->id,
                'detail' => 'Gol en juego',
            ]);
        }

        $this->command?->info('PlayerPerformanceDemoSeeder: historial demo cargado para '.$player->fullName().'.');
    }

    /**
     * @param  list<array{0: int, 1: string, 2: string}>  $events
     */
    private function seedFinishedMatch(
        int $categoryId,
        int $tournamentId,
        int $fieldId,
        Team $home,
        Team $away,
        string $scheduledAt,
        string $round,
        int $homeScore,
        int $awayScore,
        Player $player,
        int $teamId,
        array $events,
    ): void {
        $match = FixtureMatch::updateOrCreate(
            [
                'tournament_id' => $tournamentId,
                'home_team_id' => $home->id,
                'away_team_id' => $away->id,
                'scheduled_at' => $scheduledAt,
            ],
            [
                'category_id' => $categoryId,
                'field_id' => $fieldId,
                'stage' => 'Grupo A',
                'round' => $round,
                'status' => 'finished',
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'published' => true,
                'published_at' => now(),
                'referee_name' => 'Martín Sosa',
            ]
        );

        $sheet = MatchSheet::updateOrCreate(
            ['match_id' => $match->id],
            [
                'status' => 'closed',
                'validation_status' => 'validated',
                'current_step' => 4,
                'referee_name' => 'Martín Sosa',
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'locked' => true,
                'published' => true,
                'published_at' => now(),
            ]
        );

        $sheet->events()->where('player_id', $player->id)->delete();

        foreach ($events as [$minute, $type, $detail]) {
            MatchSheetEvent::create([
                'match_sheet_id' => $sheet->id,
                'type' => $type,
                'minute' => $minute,
                'player_id' => $player->id,
                'team_id' => $teamId,
                'detail' => $detail,
            ]);
        }
    }
}
