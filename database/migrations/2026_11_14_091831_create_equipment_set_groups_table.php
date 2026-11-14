<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_set_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('rank');
            $table->timestamps();

            $table->unique(['team_id', 'label']);
            $table->unique(['team_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_set_groups');
    }
};
