<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->string('summary');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->string('status')->default('needs-action');
            $table->timestamp('due_at', 6)->nullable();
            $table->timestamp('started_at', 6)->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps(6);

            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
