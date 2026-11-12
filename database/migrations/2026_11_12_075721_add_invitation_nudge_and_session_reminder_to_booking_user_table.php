<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_user', function (Blueprint $table): void {
            $table->string('invitation_nudge_tier')->nullable()->after('calendar_imip_at');
            $table->timestamp('session_reminded_at')->nullable()->after('invitation_nudge_tier');
        });
    }

    public function down(): void
    {
        Schema::table('booking_user', function (Blueprint $table): void {
            $table->dropColumn(['invitation_nudge_tier', 'session_reminded_at']);
        });
    }
};
