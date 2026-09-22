<?php

namespace App\Support;

use App\Models\Category;
use App\Models\MatchSheetIncident;
use Illuminate\Support\Collection;

class FairPlayRules
{
    /**
     * Escala oficial STC — menor puntaje = mejor comportamiento.
     *
     * @return array<string, int>
     */
    public static function scale(): array
    {
        return [
            'player_yellow' => 1,
            'player_red' => 3,
            'staff_yellow' => 2,
            'staff_expulsion' => 4,
            'family_misconduct' => 3,
            'family_expulsion' => 4,
            'adult_serious' => 5,
            'late_arrival' => 3,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return [
            'staff_yellow' => 'Amarilla DT / cuerpo técnico',
            'staff_expulsion' => 'Expulsión DT / cuerpo técnico',
            'family_misconduct' => 'Conducta inapropiada padre / familiar',
            'family_expulsion' => 'Expulsión o conducta grave padre / familiar',
            'adult_serious' => 'Incidente grave protagonizado por adulto',
            'late_arrival' => 'Llegada tarde de la delegación',
        ];
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    public static function scaleSummary(): array
    {
        return [
            ['Tarjeta amarilla a jugador', self::scale()['player_yellow']],
            ['Tarjeta roja a jugador', self::scale()['player_red']],
            ['Tarjeta amarilla a DT / cuerpo técnico', self::scale()['staff_yellow']],
            ['Expulsión DT / cuerpo técnico', self::scale()['staff_expulsion']],
            ['Conducta inapropiada padre / familiar', self::scale()['family_misconduct']],
            ['Expulsión o conducta grave padre / familiar', self::scale()['family_expulsion']],
            ['Incidente grave protagonizado por adulto', self::scale()['adult_serious']],
            ['Llegada tarde a un partido', self::scale()['late_arrival']],
        ];
    }

    /**
     * @return list<string>
     */
    public static function tiebreakerLabels(): array
    {
        return [
            'Menos expulsiones de DT / cuerpo técnico',
            'Menos expulsiones o incidentes de padres / familiares',
            'Menos sanciones totales al cuerpo técnico',
            'Menos llegadas tarde',
            'Menos tarjetas rojas de jugadores',
            'Menos tarjetas amarillas de jugadores',
        ];
    }

    public static function classifyIncident(MatchSheetIncident $incident): string
    {
        $kind = trim((string) $incident->fair_play_kind);
        if ($kind !== '' && array_key_exists($kind, self::scale())) {
            return $kind;
        }

        $text = mb_strtolower(trim(implode(' ', array_filter([
            $incident->type,
            $incident->title,
            $incident->notes,
        ]))));

        if ($text === '') {
            return 'generic';
        }

        if (preg_match('/llegad[ao].*tarde|tarde.*partido|puntualidad/', $text)) {
            return 'late_arrival';
        }

        if (preg_match('/incidente grave|extrema gravedad|agresi|amenaza|tribunal/', $text)) {
            return 'adult_serious';
        }

        if (preg_match('/padre|madre|familiar|tutor|espectador|publico|público/', $text)) {
            if (preg_match('/expuls|grave|agresi|amenaza|violent/', $text)) {
                return 'family_expulsion';
            }

            return 'family_misconduct';
        }

        if (preg_match('/dt|director t[eé]cnico|cuerpo t[eé]cnico|entrenador|staff|ayudante|preparador|kinesi|utilero|delegado/', $text)) {
            if (preg_match('/expuls|roja|grave/', $text)) {
                return 'staff_expulsion';
            }

            if (preg_match('/amarill|tarjeta/', $text)) {
                return 'staff_yellow';
            }

            return 'staff_expulsion';
        }

        if (preg_match('/fair play|incidencia|conducta/', $text)) {
            return 'family_misconduct';
        }

        return 'generic';
    }

    /**
     * @param  Collection<int, MatchSheetIncident>  $incidents
     * @return array<string, int>
     */
    public static function countIncidentKinds(Collection $incidents): array
    {
        $counts = [
            'staff_yellow' => 0,
            'staff_expulsion' => 0,
            'family_misconduct' => 0,
            'family_expulsion' => 0,
            'adult_serious' => 0,
            'late_arrival' => 0,
            'generic' => 0,
        ];

        foreach ($incidents as $incident) {
            $kind = self::classifyIncident($incident);
            $counts[$kind] = ($counts[$kind] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     */
    public static function incidentPoints(array $counts, Category $category): int
    {
        $scale = self::scale();
        $fallback = max(0, (int) ($category->fair_play_incident ?? 3));
        $points = 0;

        foreach ($counts as $kind => $count) {
            if ($kind === 'generic') {
                $points += $count * $fallback;

                continue;
            }

            $points += $count * ($scale[$kind] ?? $fallback);
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function compareRows(array $a, array $b): int
    {
        $keys = [
            fn (array $row) => $row['points'],
            fn (array $row) => $row['staff_expulsion'],
            fn (array $row) => $row['family_expulsion'] + $row['family_misconduct'],
            fn (array $row) => $row['staff_yellow'] + $row['staff_expulsion'],
            fn (array $row) => $row['late_arrival'],
            fn (array $row) => $row['reds'],
            fn (array $row) => $row['yellows'],
            fn (array $row) => mb_strtolower($row['team']->name ?? ''),
        ];

        foreach ($keys as $key) {
            $left = $key($a);
            $right = $key($b);

            if ($left === $right) {
                continue;
            }

            return $left <=> $right;
        }

        return 0;
    }

    public static function incidentAppliesToTeam(MatchSheetIncident $incident, int $teamId, string $teamName): bool
    {
        if ((int) $incident->sheet?->incident_team_id === $teamId) {
            return true;
        }

        if ($teamName === '') {
            return false;
        }

        return filled($incident->related_name)
            && str_contains(mb_strtolower((string) $incident->related_name), mb_strtolower($teamName));
    }

    public static function incidentIsDiscipline(MatchSheetIncident $incident): bool
    {
        return in_array($incident->type, ['Fair Play', 'Incidencia'], true)
            || $incident->status === 'applied';
    }
}
