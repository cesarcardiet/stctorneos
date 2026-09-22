<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('custom_modality')->nullable()->after('modality');
            $table->unsignedSmallInteger('teams_per_group')->default(4)->after('groups_count');
            $table->string('phases')->nullable()->after('qualifiers_count');
            $table->text('brackets')->nullable()->after('phases');
            $table->text('classification_criteria')->nullable()->after('brackets');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'custom_modality',
                'teams_per_group',
                'phases',
                'brackets',
                'classification_criteria',
            ]);
        });
    }
};
