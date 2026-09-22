<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('kind')->default('staff')->after('status');
            $table->string('family_status')->nullable()->after('kind');
            $table->foreignId('player_id')->nullable()->after('scope_id')->constrained()->nullOnDelete();
            $table->timestamp('accessed_at')->nullable()->after('accepted_at');
        });

        Schema::table('player_documents', function (Blueprint $table) {
            $table->date('expires_at')->nullable()->after('reviewed_at');
        });

        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->boolean('required')->default(true);
            $table->boolean('has_expiration')->default(false);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('referee_user_id')->nullable()->after('referee_name')->constrained('users')->nullOnDelete();
            $table->foreignId('assistant_user_id')->nullable()->after('referee_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('scorer_user_id')->nullable()->after('assistant_user_id')->constrained('users')->nullOnDelete();
            $table->string('period')->nullable()->after('minute');
        });

        Schema::create('match_penalty_kicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('scored')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->string('type')->default('points');
            $table->string('title');
            $table->text('resolution')->nullable();
            $table->integer('points_delta')->default(0);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('round_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('round');
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position')->nullable();
            $table->integer('rating')->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('selected')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['category_id', 'round', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_selections');
        Schema::dropIfExists('sanctions');
        Schema::dropIfExists('match_penalty_kicks');

        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scorer_user_id');
            $table->dropConstrainedForeignId('assistant_user_id');
            $table->dropConstrainedForeignId('referee_user_id');
            $table->dropColumn('period');
        });

        Schema::dropIfExists('document_requirements');

        Schema::table('player_documents', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('player_id');
            $table->dropColumn(['kind', 'family_status', 'accessed_at']);
        });
    }
};
