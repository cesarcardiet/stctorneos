<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('document_number')->nullable();
            $table->string('role');
            $table->string('photo_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['team_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_staff');
    }
};
