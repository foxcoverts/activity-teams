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
        Schema::table('venues', function (Blueprint $table) {
            $table->foreignUlid('booking_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();
            $table->index(['booking_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('venues')->whereNotNull('booking_id')->exists()) {
            throw new RuntimeException('Private booking venues must be migrated safely before this migration can be rolled back.');
        }

        Schema::table('venues', function (Blueprint $table) {
            $table->dropIndex(['booking_id', 'name']);
            $table->dropUnique(['booking_id']);
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
