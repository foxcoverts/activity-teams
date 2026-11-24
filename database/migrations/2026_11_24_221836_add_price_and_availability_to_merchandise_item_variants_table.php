<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_item_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('merchandise_item_variants', 'price')) {
                $table->decimal('price', 8, 2)->default(0)->after('label');
            }

            if (! Schema::hasColumn('merchandise_item_variants', 'availability')) {
                $table->string('availability')->default('draft')->after('price');
            }
        });

        Schema::table('merchandise_item_variants', function (Blueprint $table) {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = collect($sm->getIndexes('merchandise_item_variants'))
                ->pluck('name');

            if (! $indexes->contains('merchandise_item_variants_merchandise_item_id_availability_index')) {
                $table->index(['merchandise_item_id', 'availability']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_item_variants', function (Blueprint $table) {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = collect($sm->getIndexes('merchandise_item_variants'))
                ->pluck('name');

            if ($indexes->contains('merchandise_item_variants_merchandise_item_id_availability_index')) {
                $table->dropIndex(['merchandise_item_id', 'availability']);
            }

            if (Schema::hasColumn('merchandise_item_variants', 'availability')) {
                $table->dropColumn('availability');
            }

            if (Schema::hasColumn('merchandise_item_variants', 'price')) {
                $table->dropColumn('price');
            }
        });
    }
};
