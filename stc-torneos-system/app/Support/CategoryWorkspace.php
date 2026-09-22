<?php

namespace App\Support;

class CategoryWorkspace
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'description' => '',
            'accent_color' => '#00d5ff',
            'prizes' => [
                'first' => '',
                'second' => '',
                'third' => '',
                'other' => '',
            ],
            'highlight_first' => 3,
            'highlight_last' => 0,
            'registrations_open' => true,
            'registration_info' => '',
            'draw_rule' => 'Penales',
            'fair_play_on' => true,
            'visible_columns' => ['pts', 'j', 'g', 'e', 'p', 'gf', 'gc', 'dif', 'percent', 'pe'],
            'criteria' => [
                'Puntos',
                'Victorias',
                'Diferencia de goles',
                'Goles a Favor',
                'Goles Contra',
                'Conflicto directo',
                'Aprovechamiento',
                'W.O',
                'Tarjetas',
                'Tarjeta roja',
                'Tarjeta amarilla',
            ],
            'phases' => [
                ['name' => '1º Fase', 'mode' => 'Todos contra Todos'],
                ['name' => '2º Fase', 'mode' => 'Eliminatoria'],
            ],
            'group_names' => [],
            'officials' => [],
            'moderator_ids' => [],
            'fixture_image_path' => '',
            'team_order' => [],
            'match_order' => [],
        ];
    }

    public static function teamOrderStorageKey(?string $groupName): string
    {
        $group = strtoupper(trim(str_ireplace('grupo ', '', (string) $groupName)));

        return $group !== '' ? $group : '__general__';
    }

    public static function groupLabelToOrderKey(string $label): string
    {
        if ($label === 'Sin grupo') {
            return self::teamOrderStorageKey(null);
        }

        if (str_starts_with($label, 'Grupo ')) {
            return self::teamOrderStorageKey(substr($label, strlen('Grupo ')));
        }

        return self::teamOrderStorageKey($label);
    }

    public static function matchOrderScope(?string $phase, ?string $round): string
    {
        $phase = trim((string) $phase) ?: 'all';
        $round = trim((string) $round) ?: 'all';

        return $phase.'|'.$round;
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @param  list<int>  $teamIds
     */
    public static function saveTeamOrder(array &$workspace, string $groupKey, array $teamIds): void
    {
        $workspace['team_order'] = is_array($workspace['team_order'] ?? null) ? $workspace['team_order'] : [];
        $workspace['team_order'][$groupKey] = array_values(array_map('intval', $teamIds));
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @param  list<int>  $matchIds
     */
    public static function saveMatchOrder(array &$workspace, string $scope, array $matchIds): void
    {
        $workspace['match_order'] = is_array($workspace['match_order'] ?? null) ? $workspace['match_order'] : [];
        $workspace['match_order'][$scope] = array_values(array_map('intval', $matchIds));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $workspace
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function applyTeamDisplayOrder(\Illuminate\Support\Collection $rows, array $workspace, string $groupKey): \Illuminate\Support\Collection
    {
        $order = collect($workspace['team_order'][$groupKey] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($order->isEmpty()) {
            return $rows;
        }

        $byTeamId = $rows->keyBy(fn (array $row) => $row['team']->id);
        $ordered = $order
            ->map(fn (int $id) => $byTeamId->get($id))
            ->filter()
            ->values();
        $tail = $rows->filter(fn (array $row) => ! $order->contains($row['team']->id))->values();

        return $ordered->concat($tail)->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\FixtureMatch>  $matches
     * @param  array<string, mixed>  $workspace
     * @return \Illuminate\Support\Collection<int, \App\Models\FixtureMatch>
     */
    public static function applyMatchDisplayOrder(\Illuminate\Support\Collection $matches, array $workspace, string $scope): \Illuminate\Support\Collection
    {
        $order = collect($workspace['match_order'][$scope] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($order->isEmpty()) {
            return $matches;
        }

        $byId = $matches->keyBy('id');
        $ordered = $order->map(fn (int $id) => $byId->get($id))->filter()->values();
        $tail = $matches->filter(fn ($match) => ! $order->contains($match->id))->values();

        return $ordered->concat($tail)->values();
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return list<string>
     */
    public static function configuredRounds(array $workspace): array
    {
        return collect($workspace['rounds'] ?? [])
            ->map(fn ($round) => is_array($round) ? trim((string) ($round['name'] ?? '')) : trim((string) $round))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return list<string>
     */
    public static function editableRounds(array $workspace): array
    {
        $configured = self::configuredRounds($workspace);

        return $configured !== [] ? $configured : array_merge(self::matchdayRounds(), self::knockoutRounds());
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return list<string>
     */
    public static function phaseNames(array $workspace): array
    {
        $names = collect($workspace['phases'] ?? self::defaults()['phases'])
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        return $names !== [] ? $names : ['1º Fase', '2º Fase'];
    }

    /**
     * @return list<string>
     */
    public static function matchdayRounds(): array
    {
        return ['Fecha 1', 'Fecha 2', 'Fecha 3', 'Fecha 4', 'Fecha 5'];
    }

    /**
     * @return list<string>
     */
    public static function knockoutRounds(): array
    {
        return [
            'Octavos de final',
            'Cuartos de final',
            'Semifinal',
            'Final',
            'Final Oro',
            'Final Plata',
            'Tercer puesto',
        ];
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @param  list<string>  $existing
     * @return list<string>
     */
    public static function roundOptions(array $workspace, array $existing = []): array
    {
        $base = self::editableRounds($workspace);

        return collect($base)
            ->concat($existing)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function normalizeRound(?string $round, ?string $stage = null): string
    {
        $roundValue = trim((string) $round);
        $stageValue = trim((string) $stage);

        if (self::looksLikeKnockout($stageValue) && (! self::looksLikeKnockout($roundValue) || preg_match('/^finales$/iu', $roundValue))) {
            $roundValue = $stageValue;
        }

        $value = $roundValue;
        if ($value === '' || preg_match('/grupo/iu', $value)) {
            $value = $stageValue;
        }

        if (preg_match('/(\d+)\s*[ºo°]?\s*fecha/iu', $value, $match) || preg_match('/fecha\s*(\d+)/iu', $value, $match)) {
            return 'Fecha '.(int) $match[1];
        }

        $knockout = [
            'finales' => 'Final',
            'final bronce' => 'Tercer puesto',
            'final oro' => 'Final Oro',
            'final plata' => 'Final Plata',
            'octavos' => 'Octavos de final',
            'octavos de final' => 'Octavos de final',
            'cuartos' => 'Cuartos de final',
            'cuartos de final' => 'Cuartos de final',
            'semifinal' => 'Semifinal',
            'semi' => 'Semifinal',
            'final' => 'Final',
        ];
        $key = mb_strtolower($value);
        if (isset($knockout[$key])) {
            return $knockout[$key];
        }

        if (preg_match('/grupo|fase/iu', $value)) {
            return 'Fecha 1';
        }

        return $value !== '' ? $value : 'Fecha 1';
    }

    public static function looksLikeKnockout(string $text): bool
    {
        return (bool) preg_match('/octavos|cuartos|semi|final/iu', $text);
    }

    /**
     * @param  list<string>  $phaseNames
     */
    public static function matchPhase(\App\Models\FixtureMatch $match, array $phaseNames): string
    {
        $groupPhase = $phaseNames[0] ?? '1º Fase';
        $knockoutPhase = $phaseNames[1] ?? $groupPhase;
        $stage = trim((string) $match->stage);
        $round = self::normalizeRound($match->round, $match->stage);

        foreach ($phaseNames as $name) {
            if (strcasecmp($stage, $name) === 0) {
                return $name;
            }
        }

        $text = $stage.' '.$round;

        return preg_match('/octavos|cuartos|semi|final/iu', $text) ? $knockoutPhase : $groupPhase;
    }

    /**
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return [
            'pts' => 'Puntos',
            'j' => 'Juegos',
            'g' => 'Ganados',
            'e' => 'Empates',
            'p' => 'Perdido',
            'gf' => 'Goles a Favor',
            'gc' => 'Goles Contra',
            'dif' => 'Diferencia de Goles',
            'percent' => 'Aprovechamiento',
            'pe' => 'Por jugar',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function columnShortLabels(): array
    {
        return [
            'pts' => 'PTS',
            'j' => 'J',
            'g' => 'G',
            'e' => 'E',
            'p' => 'P',
            'gf' => 'GF',
            'gc' => 'GC',
            'dif' => 'DIF',
            'percent' => '%',
            'pe' => 'PE',
        ];
    }
}
