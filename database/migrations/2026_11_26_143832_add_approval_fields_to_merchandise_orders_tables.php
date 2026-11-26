<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_orders', function (Blueprint $table) {
            $table->decimal('order_discount', 10, 2)->default(0)->after('status');
            $table->text('rejection_reason')->nullable()->after('order_discount');
            $table->text('approval_note')->nullable()->after('rejection_reason');
            $table->timestamp('approved_at')->nullable()->after('cancelled_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });

        Schema::table('merchandise_order_lines', function (Blueprint $table) {
            $table->decimal('approved_unit_price', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_order_lines', function (Blueprint $table) {
            $table->dropColumn('approved_unit_price');
        });

        Schema::table('merchandise_orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_discount',
                'rejection_reason',
                'approval_note',
                'approved_at',
                'rejected_at',
            ]);
        });
    }
};
