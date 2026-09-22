<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;

class TournamentPurger
{
    public function delete(Tournament $tournament): void
    {
        Venue::query()->where('tournament_id', $tournament->id)->each(function (Venue $venue): void {
            $venue->delete();
        });

        User::query()->where('tournament_id', $tournament->id)->update(['tournament_id' => null]);
        User::query()->where('extra_tournament_id', $tournament->id)->update(['extra_tournament_id' => null]);

        $tournament->delete();
    }
}
