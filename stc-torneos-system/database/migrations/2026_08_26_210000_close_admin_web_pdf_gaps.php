<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('next_match_id')->nullable()->after('notes')->constrained('matches')->nullOnDelete();
            $table->string('next_slot')->nullable()->after('next_match_id');
            $table->text('reopen_reason')->nullable()->after('next_slot');
            $table->json('previous_result')->nullable()->after('reopen_reason');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('fair_play_yellow')->default(1)->after('discipline_rules');
            $table->unsignedTinyInteger('fair_play_red')->default(3)->after('fair_play_yellow');
            $table->unsignedTinyInteger('fair_play_incident')->default(2)->after('fair_play_red');
        });

        Schema::create('match_lineups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->boolean('starter')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['match_id', 'player_id']);
        });

        Schema::create('field_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('closed')->default(false);
            $table->timestamps();
            $table->unique(['field_id', 'weekday']);
        });

        Schema::create('push_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_notification_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_messages');
        Schema::dropIfExists('field_time_slots');
        Schema::dropIfExists('match_lineups');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['fair_play_yellow', 'fair_play_red', 'fair_play_incident']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('next_match_id');
            $table->dropColumn(['next_slot', 'reopen_reason', 'previous_result']);
        });
    }
};
