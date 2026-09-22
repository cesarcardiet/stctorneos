<?php

use App\Models\Tournament;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->foreignId('tournament_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $tournamentId = Tournament::query()->where('slug', 'santa-teresita-cup-2026')->value('id')
            ?: Tournament::query()->value('id');

        if ($tournamentId) {
            \Illuminate\Support\Facades\DB::table('venues')
                ->whereNull('tournament_id')
                ->update(['tournament_id' => $tournamentId]);
        }
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tournament_id');
        });
    }
};
