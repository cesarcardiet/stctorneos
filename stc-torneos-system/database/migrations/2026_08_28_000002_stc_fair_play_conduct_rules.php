<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_sheet_incidents', function (Blueprint $table) {
            $table->string('fair_play_kind', 40)->nullable()->after('status');
        });

        DB::table('categories')->update([
            'fair_play_yellow' => 1,
            'fair_play_red' => 3,
            'fair_play_incident' => 3,
        ]);

        DB::table('match_sheet_incidents')
            ->where(function ($query) {
                $query->where('title', 'like', '%padre%')
                    ->orWhere('title', 'like', '%familiar%');
            })
            ->update(['fair_play_kind' => 'family_misconduct']);
    }

    public function down(): void
    {
        Schema::table('match_sheet_incidents', function (Blueprint $table) {
            $table->dropColumn('fair_play_kind');
        });
    }
};
