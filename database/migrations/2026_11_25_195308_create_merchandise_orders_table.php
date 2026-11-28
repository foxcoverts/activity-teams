<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id', 'status']);
            $table->index(['team_id', 'status', 'submitted_at']);
        });

        Schema::create('merchandise_order_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchandise_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('merchandise_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('merchandise_item_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_title');
            $table->string('variant_label');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->json('personalisation')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['merchandise_order_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_order_lines');
        Schema::dropIfExists('merchandise_orders');
    }
};
