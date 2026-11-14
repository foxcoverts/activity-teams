<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_user', function (Blueprint $table): void {
            $table->timestamp('logbook_ignored_at')->nullable()->after('attendance_confirmed_at');
            $table->timestamp('logbook_nudged_at')->nullable()->after('logbook_ignored_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_user', function (Blueprint $table): void {
            $table->dropColumn(['logbook_ignored_at', 'logbook_nudged_at']);
        });
    }
};
