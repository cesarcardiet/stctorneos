<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('address')->nullable()->after('nationality');
            $table->string('preferred_foot')->nullable()->after('jersey_number');
            $table->string('height')->nullable()->after('preferred_foot');
            $table->string('weight')->nullable()->after('height');
            $table->string('blood_type')->nullable()->after('weight');
            $table->string('medical_coverage')->nullable()->after('blood_type');
            $table->string('allergies')->nullable()->after('medical_coverage');
            $table->string('medication')->nullable()->after('allergies');
            $table->string('illnesses')->nullable()->after('medication');
            $table->string('restrictions')->nullable()->after('illnesses');
            $table->string('emergency_contact')->nullable()->after('restrictions');
            $table->string('observation_reason')->nullable()->after('notes');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('alternate_contact')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'preferred_foot',
                'height',
                'weight',
                'blood_type',
                'medical_coverage',
                'allergies',
                'medication',
                'illnesses',
                'restrictions',
                'emergency_contact',
                'observation_reason',
            ]);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn('alternate_contact');
        });
    }
};
