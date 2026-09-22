<?php

use App\Models\Team;
use App\Support\Countries;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Team::query()
            ->with(['delegation', 'tournament'])
            ->where(function ($query) {
                $query->whereNull('country_code')->orWhere('country_code', '');
            })
            ->chunkById(100, function ($teams) {
                foreach ($teams as $team) {
                    $code = Countries::guessCode(
                        $team->delegation?->country,
                        $team->delegation_name,
                        $team->tournament?->country,
                    ) ?: 'AR';

                    $team->forceFill(['country_code' => $code])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        //
    }
};
