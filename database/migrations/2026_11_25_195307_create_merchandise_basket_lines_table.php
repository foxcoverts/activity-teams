<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_basket_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('merchandise_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('merchandise_item_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->json('personalisation')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_basket_lines');
    }
};
