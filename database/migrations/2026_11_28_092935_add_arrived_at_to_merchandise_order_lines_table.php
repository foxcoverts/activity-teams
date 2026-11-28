<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_order_lines', function (Blueprint $table) {
            $table->timestamp('arrived_at')->nullable()->after('merchandise_supplier_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_order_lines', function (Blueprint $table) {
            $table->dropColumn('arrived_at');
        });
    }
};
