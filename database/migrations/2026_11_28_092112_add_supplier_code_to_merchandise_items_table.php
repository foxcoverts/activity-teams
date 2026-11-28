<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_items', function (Blueprint $table) {
            $table->string('supplier_code')
                ->nullable()
                ->after('merchandise_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_items', function (Blueprint $table) {
            $table->dropColumn('supplier_code');
        });
    }
};
