<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_user', function (Blueprint $table): void {
            $table->timestamp('attendance_confirmed_at')->nullable()->after('session_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_user', function (Blueprint $table): void {
            $table->dropColumn('attendance_confirmed_at');
        });
    }
};
