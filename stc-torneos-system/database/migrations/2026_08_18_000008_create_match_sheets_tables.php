<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('validation_status')->default('pending');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->string('referee_name')->nullable();
            $table->string('assistant_name')->nullable();
            $table->string('responsible_name')->nullable();
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->string('incident_title')->nullable();
            $table->foreignId('incident_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('incident_moment')->nullable();
            $table->text('incident_notes')->nullable();
            $table->boolean('locked')->default(false);
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('match_sheet_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_sheet_id')->constrained('match_sheets')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedSmallInteger('minute')->nullable();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('detail')->nullable();
            $table->timestamps();
        });

        Schema::create('match_sheet_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_sheet_id')->constrained('match_sheets')->cascadeOnDelete();
            $table->string('type')->default('Incidencia');
            $table->string('title');
            $table->string('related_name')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('match_sheet_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_sheet_id')->constrained('match_sheets')->cascadeOnDelete();
            $table->string('role');
            $table->string('name');
            $table->boolean('signed')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_sheet_signatures');
        Schema::dropIfExists('match_sheet_incidents');
        Schema::dropIfExists('match_sheet_events');
        Schema::dropIfExists('match_sheets');
    }
};
