<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
            $table->dropConstrainedForeignId('holder_id');
        });

        Schema::create('key_requirements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('key_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['key_id', 'activity_id', 'venue_id']);
        });

        Schema::create('key_covers', function (Blueprint $table) {
            $table->foreignUlid('key_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('covered_key_id')->constrained('keys')->cascadeOnDelete();
            $table->primary(['key_id', 'covered_key_id']);
        });

        Schema::create('key_rings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->foreignUlid('holder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('key_ring_key', function (Blueprint $table) {
            $table->foreignUlid('key_ring_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('key_id')->constrained()->cascadeOnDelete();
            $table->primary(['key_ring_id', 'key_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_ring_key');
        Schema::dropIfExists('key_rings');
        Schema::dropIfExists('key_covers');
        Schema::dropIfExists('key_requirements');

        Schema::table('keys', function (Blueprint $table) {
            $table->foreignUlid('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('holder_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
