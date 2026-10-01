<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_lineups', function (Blueprint $table) {
            $table->unsignedTinyInteger('shirt_number')->nullable()->after('starter');
            $table->unsignedTinyInteger('pos_x')->nullable()->after('sort_order');
            $table->unsignedTinyInteger('pos_y')->nullable()->after('pos_x');
        });
    }

    public function down(): void
    {
        Schema::table('match_lineups', function (Blueprint $table) {
            $table->dropColumn(['shirt_number', 'pos_x', 'pos_y']);
        });
    }
};
