<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_documents', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('file_path');
            $table->string('uploaded_by_name')->nullable()->after('original_name');
            $table->timestamp('uploaded_at')->nullable()->after('uploaded_by_name');
            $table->json('checklist')->nullable()->after('notes');
            $table->timestamp('reviewed_at')->nullable()->after('checklist');
        });
    }

    public function down(): void
    {
        Schema::table('player_documents', function (Blueprint $table) {
            $table->dropColumn([
                'original_name',
                'uploaded_by_name',
                'uploaded_at',
                'checklist',
                'reviewed_at',
            ]);
        });
    }
};
