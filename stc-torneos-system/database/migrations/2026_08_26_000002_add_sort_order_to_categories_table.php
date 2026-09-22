<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('status');
        });

        $rows = DB::table('categories')
            ->orderBy('tournament_id')
            ->orderBy('birth_year')
            ->orderBy('name')
            ->get(['id', 'tournament_id']);

        $lastTournament = null;
        $index = 0;
        foreach ($rows as $row) {
            if ($lastTournament !== $row->tournament_id) {
                $lastTournament = $row->tournament_id;
                $index = 0;
            }

            DB::table('categories')->where('id', $row->id)->update(['sort_order' => $index]);
            $index++;
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
