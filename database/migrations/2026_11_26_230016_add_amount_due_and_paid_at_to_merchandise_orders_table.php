<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_orders', function (Blueprint $table) {
            $table->decimal('amount_due', 10, 2)->default(0)->after('order_discount');
            $table->timestamp('paid_at')->nullable()->after('approved_at');
            $table->index(['team_id', 'status', 'paid_at', 'amount_due'], 'merchandise_orders_unpaid_index');
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_orders', function (Blueprint $table) {
            $table->dropIndex('merchandise_orders_unpaid_index');
            $table->dropColumn(['amount_due', 'paid_at']);
        });
    }
};
