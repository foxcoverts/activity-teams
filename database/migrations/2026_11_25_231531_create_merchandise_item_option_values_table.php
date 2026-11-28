<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_item_option_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('merchandise_item_option_axis_id');
            $table->string('label');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->foreign('merchandise_item_option_axis_id', 'merchandise_option_values_axis_fk')
                ->references('id')
                ->on('merchandise_item_option_axes')
                ->cascadeOnDelete();
            $table->index(['merchandise_item_option_axis_id', 'sort'], 'merchandise_option_values_axis_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_item_option_values');
    }
};
