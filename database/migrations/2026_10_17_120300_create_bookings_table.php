<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('activity_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('venue_id')->constrained()->restrictOnDelete();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('timezone', 100)->nullable();
            $table->string('status')->default('tentative');
            $table->string('group_name');
            $table->text('notes')->nullable();
            $table->foreignUlid('lead_instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('lead_instructor_notes')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('start_at');
        });

        Schema::create('booking_user', function (Blueprint $table) {
            $table->foreignUlid('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('needs-action');
            $table->text('comment')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->timestamps();

            $table->unique(['booking_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_user');
        Schema::dropIfExists('bookings');
    }
};
