<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignUlid('activity_id')->nullable()->change();
            $table->foreignUlid('audience_id')->nullable()->change();
            $table->string('activity_label')->nullable()->after('audience_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            DB::table('bookings')
                ->whereNull('activity_id')
                ->orWhereNull('audience_id')
                ->orWhereNotNull('activity_label')
                ->exists()
        ) {
            throw new RuntimeException('Custom booking activities and labels must be migrated safely before this migration can be rolled back.');
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('activity_label');
            $table->foreignUlid('activity_id')->nullable(false)->change();
            $table->foreignUlid('audience_id')->nullable(false)->change();
        });
    }
};
