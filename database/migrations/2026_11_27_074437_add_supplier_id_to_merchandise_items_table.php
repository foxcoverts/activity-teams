<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_items', function (Blueprint $table) {
            $table->foreignUlid('merchandise_supplier_id')
                ->nullable()
                ->after('team_id')
                ->constrained('merchandise_suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchandise_supplier_id');
        });
    }
};
