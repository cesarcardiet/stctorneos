<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Delegation;
use App\Models\FixtureMatch;
use App\Models\Player;
use App\Models\Tournament;

class InvitationScope
{
    public const SYSTEM = 'system';

    public const TOURNAMENT = 'tournament';

    public const CATEGORY = 'category';

    public const DELEGATION = 'delegation';

    public const TEAM = 'team';

    public const PLAYER = 'player';

    public const MATCH = 'match';

    public static function normalize(?string $scopeType): ?string
    {
        if ($scopeType === null || $scopeType === '') {
            return null;
        }

        if ($scopeType === Player::class || $scopeType === 'App\\Models\\Player') {
            return self::PLAYER;
        }

        return match ($scopeType) {
            self::SYSTEM,
            self::TOURNAMENT,
            self::CATEGORY,
            self::DELEGATION,
            self::TEAM,
            self::PLAYER,
            self::MATCH => $scopeType,
            default => $scopeType,
        };
    }

    /**
     * @return array{type: ?string, id: ?int, label: ?string, tournament: ?array{id: int, name: string}}
     */
    public static function describe(?string $scopeType, ?int $scopeId): array
    {
        $type = self::normalize($scopeType);
        $label = null;
        $tournament = null;

        if ($type === null || $scopeId === null) {
            return [
                'type' => $type,
                'id' => $scopeId,
                'label' => $label,
                'tournament' => $tournament,
            ];
        }

        switch ($type) {
            case self::TOURNAMENT:
                $entity = Tournament::query()->find($scopeId);
                $label = $entity?->name;
                $tournament = $entity ? ['id' => $entity->id, 'name' => $entity->name] : null;
                break;
            case self::CATEGORY:
                $entity = Category::query()->with('tournament')->find($scopeId);
                $label = $entity?->name;
                $tournament = $entity?->tournament
                    ? ['id' => $entity->tournament->id, 'name' => $entity->tournament->name]
                    : null;
                break;
            case self::DELEGATION:
                $entity = Delegation::query()->with('tournament')->find($scopeId);
                $label = $entity?->name;
                $tournament = $entity?->tournament
                    ? ['id' => $entity->tournament->id, 'name' => $entity->tournament->name]
                    : null;
                break;
            case self::PLAYER:
                $entity = Player::query()->with('team.category.tournament')->find($scopeId);
                $label = $entity?->fullName();
                $tournamentEntity = $entity?->team?->tournament;
                $tournament = $tournamentEntity
                    ? ['id' => $tournamentEntity->id, 'name' => $tournamentEntity->name]
                    : null;
                break;
            case self::MATCH:
                $entity = FixtureMatch::query()->with('category.tournament')->find($scopeId);
                $label = $entity?->title();
                $tournamentEntity = $entity?->category?->tournament;
                $tournament = $tournamentEntity
                    ? ['id' => $tournamentEntity->id, 'name' => $tournamentEntity->name]
                    : null;
                break;
            case self::SYSTEM:
                $label = 'Sistema';
                break;
        }

        return [
            'type' => $type,
            'id' => $scopeId,
            'label' => $label,
            'tournament' => $tournament,
        ];
    }
}
