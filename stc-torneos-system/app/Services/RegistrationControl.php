<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Tournament;

class RegistrationControl
{
    public static function setCategoryOpen(Category $category, bool $open, ?string $registrationInfo = null): void
    {
        $patch = ['registrations_open' => $open];
        if ($registrationInfo !== null) {
            $patch['registration_info'] = $registrationInfo;
        }

        $category->mergeWorkspace($patch);
        $category->teams()->update(['roster_open' => $open]);
    }

    public static function setTournamentOpen(Tournament $tournament, bool $open): void
    {
        $tournament->loadMissing('categories');

        foreach ($tournament->categories as $category) {
            self::setCategoryOpen($category, $open);
        }
    }
}
