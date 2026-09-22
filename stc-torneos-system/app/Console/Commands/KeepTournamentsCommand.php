<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Console\Command;

class KeepTournamentsCommand extends Command
{
    protected $signature = 'stc:keep-tournaments';

    protected $description = 'Deja solo Santa Teresita Cup 2026 y STC Buenos Aires 2026';

    public function handle(): int
    {
        $keepSlugs = [
            'santa-teresita-cup-2026',
            'stc-buenos-aires-2026',
        ];

        $keepIds = Tournament::query()->whereIn('slug', $keepSlugs)->pluck('id');
        $extras = Tournament::query()->whereNotIn('slug', $keepSlugs)->get();

        foreach ($extras as $tournament) {
            Venue::query()->where('tournament_id', $tournament->id)->each(function (Venue $venue): void {
                $venue->delete();
            });
            $this->info('Eliminado: '.$tournament->name);
            $tournament->delete();
        }

        $buenosAiresId = Tournament::query()->where('slug', 'stc-buenos-aires-2026')->value('id');
        if ($buenosAiresId) {
            User::query()
                ->whereNotNull('extra_tournament_id')
                ->whereNotIn('extra_tournament_id', $keepIds->all() ?: [0])
                ->update(['extra_tournament_id' => $buenosAiresId]);
        }

        $this->info('Quedan: '.Tournament::query()->orderBy('name')->pluck('name')->implode(', '));

        return self::SUCCESS;
    }
}
