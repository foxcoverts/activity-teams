<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('mail_preferences')->nullable()->after('ical_token');
            $table->string('mail_preference_token')->nullable()->unique()->after('mail_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mail_preferences', 'mail_preference_token']);
        });
    }
};
