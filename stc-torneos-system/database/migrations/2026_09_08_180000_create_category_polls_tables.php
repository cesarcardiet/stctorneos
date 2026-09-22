<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question', 500);
            $table->boolean('is_visible')->default(true);
            $table->boolean('show_results')->default(true);
            $table->boolean('voting_open')->default(true);
            $table->boolean('allow_multiple')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_poll_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('voter_key', 120);
            $table->timestamps();

            $table->unique(['category_poll_id', 'category_poll_option_id', 'voter_key'], 'category_poll_votes_unique');
            $table->index(['category_poll_id', 'voter_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_poll_votes');
        Schema::dropIfExists('category_poll_options');
        Schema::dropIfExists('category_polls');
    }
};
