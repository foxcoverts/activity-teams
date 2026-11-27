<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_item_variants', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_item_variants', function (Blueprint $table) {
            $table->dropColumn('stock_quantity');
        });
    }
};
