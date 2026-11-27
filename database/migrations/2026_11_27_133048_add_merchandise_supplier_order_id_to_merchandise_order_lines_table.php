<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_order_lines', function (Blueprint $table) {
            $table->foreignUlid('merchandise_supplier_order_id')
                ->nullable()
                ->after('merchandise_item_variant_id')
                ->constrained('merchandise_supplier_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchandise_supplier_order_id');
        });
    }
};
