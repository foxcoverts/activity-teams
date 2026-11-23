<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_merchandise', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('merchandise_item_variant_id')->constrained()->restrictOnDelete();
            $table->date('issued_on');
            $table->text('note')->nullable();
            $table->string('source');
            $table->timestamps();

            $table->index(['team_id', 'user_id', 'issued_on']);
            $table->index(['team_id', 'merchandise_item_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_merchandise');
    }
};
