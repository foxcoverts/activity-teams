<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('debrief_nudged_at')->nullable()->after('sequence');
            $table->timestamp('debrief_week_nudged_at')->nullable()->after('debrief_nudged_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['debrief_nudged_at', 'debrief_week_nudged_at']);
        });
    }
};
