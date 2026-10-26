<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_related', function (Blueprint $table) {
            $table->foreignUlid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUlid('related_id')->constrained('bookings')->cascadeOnDelete();

            $table->primary(['booking_id', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_related');
    }
};
