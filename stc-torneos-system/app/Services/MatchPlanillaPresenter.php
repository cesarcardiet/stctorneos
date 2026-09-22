<?php

namespace App\Services;

use App\Models\FixtureMatch;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Support\CategoryWorkspace;
use Illuminate\Support\Collection;

class MatchPlanillaPresenter
{
    public const ROSTER_SLOTS = 23;

    public const GOAL_SLOTS = 28;

    public const SUB_SLOTS = 9;

    /**
     * @return array<string, mixed>
     */
    public function forMatch(FixtureMatch $match): array
    {
        $match->loadMissing([
            'tournament',
            'category',
            'field.venue',
            'sheet',
            'homeTeam.players',
            'awayTeam.players',
            'homeTeam.staffMembers',
            'awayTeam.staffMembers',
        ]);

        $workspace = $match->category?->workspace() ?? [];
        $phases = CategoryWorkspace::phaseNames($workspace);
        [$homeScore, $awayScore] = $match->officialScores();

        return [
            'match' => $match,
            'meta' => [
                'competition' => $match->tournament?->name ?: '—',
                'category' => $match->category?->name ?: '—',
                'phase' => CategoryWorkspace::matchPhase($match, $phases) ?: ($match->stage ?: '—'),
                'round' => CategoryWorkspace::normalizeRound($match->round, $match->stage) ?: '—',
                'juego' => '',
                'venue' => trim(collect([
                    $match->field?->name,
                    $match->field?->venue?->name,
                ])->filter()->implode(' · ')) ?: '—',
                'datetime' => $this->dateLabel($match),
                'home_name' => $match->homeTeam?->name ?: '—',
                'away_name' => $match->awayTeam?->name ?: '—',
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'referee' => trim(collect([
                    $match->sheet?->referee_name ?: $match->referee_name,
                    filled($match->sheet?->assistant_name) ? 'Asistente: '.$match->sheet->assistant_name : null,
                ])->filter()->implode(' · ')),
            ],
            'home' => $this->teamBlock($match->homeTeam, $homeScore),
            'away' => $this->teamBlock($match->awayTeam, $awayScore),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamBlock(?Team $team, ?int $score = null): array
    {
        $goalMarks = [];
        if ($score !== null && $score > 0) {
            for ($i = 1; $i <= min($score, self::GOAL_SLOTS); $i++) {
                $goalMarks[$i] = true;
            }
        }

        return [
            'name' => $team?->name ?: '—',
            'coach' => $this->coachName($team),
            'captain' => '',
            'rows' => $this->rosterPlayers($team?->players ?? collect()),
            'goal_marks' => $goalMarks,
        ];
    }

    /**
     * @param  Collection<int, Player>  $players
     * @return list<array{jersey: string, document: string, name: string}>
     */
    private function rosterPlayers(Collection $players): array
    {
        $sorted = $players
            ->sortBy(fn (Player $player) => sprintf(
                '%05d-%s-%s',
                is_numeric($player->jersey_number) ? (int) $player->jersey_number : 99999,
                mb_strtolower($player->last_name ?? ''),
                mb_strtolower($player->first_name ?? ''),
            ))
            ->values();

        $rows = [];

        for ($slot = 1; $slot <= self::ROSTER_SLOTS; $slot++) {
            $player = $sorted->get($slot - 1);
            $rows[] = [
                'jersey' => $player && filled($player->jersey_number)
                    ? (string) (int) $player->jersey_number
                    : '',
                'document' => $player ? $this->documentLabel($player) : '',
                'name' => $player?->fullName() ?? '',
            ];
        }

        return $rows;
    }

    private function documentLabel(Player $player): string
    {
        $digits = preg_replace('/\D+/', '', (string) $player->document_number);

        return $digits !== '' ? $digits : '';
    }

    private function coachName(?Team $team): string
    {
        if (! $team) {
            return '';
        }

        $coach = $team->staffMembers
            ->filter(fn (TeamStaff $member) => $member->isActive())
            ->first(fn (TeamStaff $member) => $member->role === 'director_tecnico')
            ?? $team->staffMembers->first(fn (TeamStaff $member) => $member->isActive());

        return $coach?->fullName() ?? '';
    }

    private function dateLabel(FixtureMatch $match): string
    {
        if (! $match->scheduled_at) {
            return '—';
        }

        $days = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
        $months = [
            1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
            7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
        ];

        $at = $match->scheduled_at;

        return $at->format('j').' '.$months[(int) $at->format('n')].' | '.$days[(int) $at->format('w')].' - '.$at->format('G:i');
    }
}
