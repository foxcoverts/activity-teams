<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_equipment_set', function (Blueprint $table): void {
            $table->foreignUlid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('equipment_set_id')->constrained()->cascadeOnDelete();

            $table->primary(['activity_id', 'equipment_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_equipment_set');
    }
};
