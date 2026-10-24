<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacy_notice_accepted_at')->nullable()->after('email_verified_at');
            $table->timestamp('profile_confirmed_at')->nullable()->after('privacy_notice_accepted_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['privacy_notice_accepted_at', 'profile_confirmed_at']);
            $table->dropSoftDeletes();
        });
    }
};
