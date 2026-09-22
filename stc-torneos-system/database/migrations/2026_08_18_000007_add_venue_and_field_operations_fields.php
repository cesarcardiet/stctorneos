<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('map_url')->nullable()->after('address');
            $table->text('notes')->nullable()->after('status');
        });

        Schema::table('fields', function (Blueprint $table) {
            $table->string('modality')->nullable()->after('surface');
            $table->boolean('lighting')->default(true)->after('modality');
            $table->time('opens_at')->nullable()->after('lighting');
            $table->time('closes_at')->nullable()->after('opens_at');
            $table->text('notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['map_url', 'notes']);
        });

        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn(['modality', 'lighting', 'opens_at', 'closes_at', 'notes']);
        });
    }
};
