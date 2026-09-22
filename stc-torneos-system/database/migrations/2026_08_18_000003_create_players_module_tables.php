<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('document_number')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nationality')->default('Argentina');
            $table->string('position')->nullable();
            $table->unsignedTinyInteger('jersey_number')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('medical_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->default('Padre/Madre');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('player_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_documents');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('players');
    }
};
