<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_debrief_equipment_set', function (Blueprint $table): void {
            $table->foreignUlid('booking_debrief_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('equipment_set_id')->constrained()->cascadeOnDelete();

            $table->primary(['booking_debrief_id', 'equipment_set_id'], 'booking_debrief_equipment_set_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_debrief_equipment_set');
    }
};
