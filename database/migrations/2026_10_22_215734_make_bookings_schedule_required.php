<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')->whereNull('timezone')->update(['timezone' => 'Europe/London']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('timezone', 100)->nullable(false)->change();
            $table->timestamp('start_at')->nullable(false)->change();
            $table->timestamp('end_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('timezone', 100)->nullable()->change();
            $table->timestamp('start_at')->nullable()->change();
            $table->timestamp('end_at')->nullable()->change();
        });
    }
};
