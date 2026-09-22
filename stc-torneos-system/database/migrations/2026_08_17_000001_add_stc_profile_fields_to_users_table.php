<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('status')->default('active')->after('password');
            $table->string('current_scope')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('current_scope');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'status', 'current_scope', 'last_login_at']);
        });
    }
};
