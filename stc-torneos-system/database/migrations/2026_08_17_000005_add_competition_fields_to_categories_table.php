<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('branch')->default('Masculina')->after('birth_year');
            $table->string('modality')->default('Fútbol 11')->after('branch');
            $table->unsignedSmallInteger('min_players')->default(11)->after('team_limit');
            $table->unsignedSmallInteger('max_players')->default(22)->after('min_players');
            $table->unsignedSmallInteger('players_on_field')->default(11)->after('max_players');
            $table->unsignedSmallInteger('substitutes')->default(7)->after('players_on_field');
            $table->unsignedTinyInteger('periods')->default(2)->after('substitutes');
            $table->unsignedSmallInteger('period_duration')->default(25)->after('periods');
            $table->string('competition_format')->default('Grupos y finales')->after('period_duration');
            $table->unsignedSmallInteger('groups_count')->default(4)->after('competition_format');
            $table->unsignedSmallInteger('qualifiers_count')->default(8)->after('groups_count');
            $table->unsignedTinyInteger('points_win')->default(3)->after('qualifiers_count');
            $table->unsignedTinyInteger('points_draw')->default(1)->after('points_win');
            $table->unsignedTinyInteger('points_loss')->default(0)->after('points_draw');
            $table->json('tiebreakers')->nullable()->after('points_loss');
            $table->text('rules')->nullable()->after('tiebreakers');
            $table->text('discipline_rules')->nullable()->after('rules');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'branch',
                'modality',
                'min_players',
                'max_players',
                'players_on_field',
                'substitutes',
                'periods',
                'period_duration',
                'competition_format',
                'groups_count',
                'qualifiers_count',
                'points_win',
                'points_draw',
                'points_loss',
                'tiebreakers',
                'rules',
                'discipline_rules',
            ]);
        });
    }
};
