<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InscriptionQueue
{
    /**
     * @return list<string>
     */
    public static function waitingStatuses(): array
    {
        return Player::waitingInscriptionStatuses();
    }

    /**
     * @return Collection<int, Player>
     */
    public function waiting(Category $category, ?User $user = null): Collection
    {
        return $this->baseQuery($category, $user)
            ->whereIn('status', self::waitingStatuses())
            ->orderByDesc('updated_at')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * @return Collection<int, Player>
     */
    public function rejected(Category $category, ?User $user = null): Collection
    {
        return $this->baseQuery($category, $user)
            ->where('status', 'rejected')
            ->orderByDesc('updated_at')
            ->orderBy('last_name')
            ->get();
    }

    public function waitingCount(Category $category, ?User $user = null): int
    {
        return $this->baseQuery($category, $user)
            ->whereIn('status', self::waitingStatuses())
            ->count();
    }

    public function rejectedCount(Category $category, ?User $user = null): int
    {
        return $this->baseQuery($category, $user)
            ->where('status', 'rejected')
            ->count();
    }

    /**
     * @return Builder<Player>
     */
    private function baseQuery(Category $category, ?User $user = null): Builder
    {
        return Player::query()
            ->with(['team.delegation', 'guardian', 'documents'])
            ->whereHas('team', function (Builder $query) use ($category, $user) {
                $query->where('category_id', $category->id);

                if ($user?->restrictsToAssignedClub()) {
                    $query->accessibleTo($user);
                }
            });
    }
}
