<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tournament_id')->nullable()->after('current_scope')->constrained()->nullOnDelete();
            $table->foreignId('extra_tournament_id')->nullable()->after('tournament_id')->constrained('tournaments')->nullOnDelete();
            $table->string('suspension_reason')->nullable()->after('extra_tournament_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tournament_id');
            $table->dropConstrainedForeignId('extra_tournament_id');
            $table->dropColumn('suspension_reason');
        });
    }
};
