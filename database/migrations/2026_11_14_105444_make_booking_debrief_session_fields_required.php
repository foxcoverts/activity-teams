<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('booking_debriefs')->whereNull('hours')->update(['hours' => 0]);
        DB::table('booking_debriefs')->whereNull('participant_count')->update(['participant_count' => 0]);
        DB::table('booking_debriefs')->whereNull('weather')->update(['weather' => 'Unknown']);

        Schema::table('booking_debriefs', function (Blueprint $table): void {
            $table->decimal('hours', 5, 2)->nullable(false)->change();
            $table->unsignedInteger('participant_count')->nullable(false)->change();
            $table->string('weather')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('booking_debriefs', function (Blueprint $table): void {
            $table->decimal('hours', 5, 2)->nullable()->change();
            $table->unsignedInteger('participant_count')->nullable()->change();
            $table->string('weather')->nullable()->change();
        });
    }
};
