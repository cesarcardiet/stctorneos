<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('format');
        });

        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('country')->default('Argentina');
            $table->string('city')->nullable();
            $table->string('delegate_name');
            $table->string('delegate_email')->nullable();
            $table->string('delegate_phone')->nullable();
            $table->string('status')->default('approved');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('delegation_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->string('group_name')->nullable()->after('city');
            $table->string('home_kit')->nullable()->after('group_name');
            $table->string('away_kit')->nullable()->after('home_kit');
            $table->unsignedSmallInteger('player_capacity')->default(14)->after('away_kit');
            $table->string('shield_path')->nullable()->after('player_capacity');
            $table->text('notes')->nullable()->after('shield_path');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delegation_id');
            $table->dropColumn(['group_name', 'home_kit', 'away_kit', 'player_capacity', 'shield_path', 'notes']);
        });

        Schema::dropIfExists('delegations');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
