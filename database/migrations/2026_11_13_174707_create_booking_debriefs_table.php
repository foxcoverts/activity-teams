<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_debriefs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('hours', 5, 2)->nullable();
            $table->unsignedInteger('participant_count')->nullable();
            $table->string('weather')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('completed_at');
            $table->foreignUlid('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_debriefs');
    }
};
