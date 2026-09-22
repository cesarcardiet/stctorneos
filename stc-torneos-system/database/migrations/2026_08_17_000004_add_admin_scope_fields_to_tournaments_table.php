<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('edition')->nullable()->after('name');
            $table->string('logo_path')->nullable()->after('slug');
            $table->string('country')->default('Argentina')->after('logo_path');
            $table->string('city')->nullable()->after('country');
            $table->string('venue_name')->nullable()->after('city');
            $table->string('timezone')->default('America/Argentina/Buenos_Aires')->after('venue_name');
            $table->string('contact_name')->nullable()->after('description');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->text('rules_url')->nullable()->after('contact_phone');
            $table->text('general_info')->nullable()->after('rules_url');
            $table->string('visibility')->default('private')->after('general_info');
            $table->date('registration_starts_at')->nullable()->after('visibility');
            $table->date('registration_ends_at')->nullable()->after('registration_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'edition',
                'logo_path',
                'country',
                'city',
                'venue_name',
                'timezone',
                'contact_name',
                'contact_email',
                'contact_phone',
                'rules_url',
                'general_info',
                'visibility',
                'registration_starts_at',
                'registration_ends_at',
            ]);
        });
    }
};
