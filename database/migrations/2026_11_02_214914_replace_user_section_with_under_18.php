<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('under_18')->default(false)->after('section');
        });

        DB::table('users')
            ->whereIn('section', ['squirrel', 'beaver', 'cub', 'scout', 'explorer'])
            ->update(['under_18' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('section')->default('adult')->after('under_18');
        });

        DB::table('users')
            ->where('under_18', true)
            ->update(['section' => 'scout']);

        // The original youth section cannot be reconstructed from the safeguarding flag.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('under_18');
        });
    }
};
