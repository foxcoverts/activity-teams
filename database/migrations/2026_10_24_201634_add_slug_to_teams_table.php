<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        DB::table('teams')
            ->orderBy('id')
            ->each(function (object $team): void {
                $base = Str::slug((string) $team->name);
                if ($base === '') {
                    $base = 'team';
                }

                $slug = $base;
                $suffix = 2;

                while (
                    DB::table('teams')
                        ->where('slug', $slug)
                        ->where('id', '!=', $team->id)
                        ->exists()
                ) {
                    $slug = $base.'-'.$suffix;
                    $suffix++;
                }

                DB::table('teams')
                    ->where('id', $team->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
