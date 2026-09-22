<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('round')->nullable()->after('stage');
            $table->unsignedSmallInteger('duration_minutes')->default(70)->after('minute');
            $table->string('referee_name')->nullable()->after('duration_minutes');
            $table->boolean('published')->default(false)->after('referee_name');
            $table->timestamp('published_at')->nullable()->after('published');
            $table->text('notes')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn([
                'round',
                'duration_minutes',
                'referee_name',
                'published',
                'published_at',
                'notes',
            ]);
        });
    }
};
