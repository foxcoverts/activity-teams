<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_user', function (Blueprint $table) {
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('needs-action');
            $table->text('comment')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->timestamp('calendar_imip_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user');
    }
};
