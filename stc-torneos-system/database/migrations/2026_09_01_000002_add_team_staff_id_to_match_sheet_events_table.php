<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_sheet_events', function (Blueprint $table) {
            $table->foreignId('team_staff_id')
                ->nullable()
                ->after('player_id')
                ->constrained('team_staff')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('match_sheet_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_staff_id');
        });
    }
};
