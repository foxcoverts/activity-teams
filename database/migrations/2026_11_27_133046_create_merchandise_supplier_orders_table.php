<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_supplier_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('merchandise_supplier_id')->constrained('merchandise_suppliers')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            // Unique while draft (sent rows clear this). MariaDB-friendly stand-in for a partial unique index.
            $table->string('open_draft_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['team_id', 'merchandise_supplier_id'], 'merchandise_supplier_orders_team_supplier_idx');
            $table->index(['team_id', 'sent_at'], 'merchandise_supplier_orders_team_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_supplier_orders');
    }
};
