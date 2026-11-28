<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_item_option_axes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchandise_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['merchandise_item_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_item_option_axes');
    }
};
