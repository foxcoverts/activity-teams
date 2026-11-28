<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_item_variant_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('merchandise_item_variant_id');
            $table->ulid('merchandise_item_option_value_id');
            $table->timestamps();

            $table->foreign('merchandise_item_variant_id', 'merchandise_variant_options_variant_fk')
                ->references('id')
                ->on('merchandise_item_variants')
                ->cascadeOnDelete();
            $table->foreign('merchandise_item_option_value_id', 'merchandise_variant_options_value_fk')
                ->references('id')
                ->on('merchandise_item_option_values')
                ->cascadeOnDelete();
            $table->unique(
                ['merchandise_item_variant_id', 'merchandise_item_option_value_id'],
                'merchandise_variant_options_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_item_variant_options');
    }
};
