<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('news');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary')->nullable();
            $table->text('body')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('audience')->default('public');
            $table->string('status')->default('draft');
            $table->boolean('pinned')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_post_id')->nullable()->constrained('content_posts')->cascadeOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('image');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_post_id')->nullable()->constrained('content_posts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel')->default('in_app');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('audience')->default('all');
            $table->string('status')->default('draft');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('app_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_notification_id')->constrained('app_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role_name')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('favorite_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'team_id']);
        });

        Schema::create('favorite_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_matches');
        Schema::dropIfExists('favorite_teams');
        Schema::dropIfExists('app_notification_recipients');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('content_posts');
    }
};
