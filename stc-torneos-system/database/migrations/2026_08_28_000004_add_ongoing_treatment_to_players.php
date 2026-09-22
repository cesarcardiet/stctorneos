<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('ongoing_treatment')->nullable()->after('vaccination_calendar_complete');
            $table->text('ongoing_treatment_notes')->nullable()->after('ongoing_treatment');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['ongoing_treatment', 'ongoing_treatment_notes']);
        });
    }
};
