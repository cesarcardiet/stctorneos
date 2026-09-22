<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegation_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'delegation_id']);
        });

        DB::table('users')
            ->whereNotNull('delegation_id')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('delegation_user')->insertOrIgnore([
                    'user_id' => $row->id,
                    'delegation_id' => $row->delegation_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegation_user');
    }
};
