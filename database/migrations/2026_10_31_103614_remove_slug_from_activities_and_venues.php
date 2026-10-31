<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropSlugColumn('activities');
        $this->dropSlugColumn('venues');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->addSlugColumn('activities');
        $this->addSlugColumn('venues');
    }

    protected function dropSlugColumn(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }

    protected function addSlugColumn(string $tableName, string $nameColumn = 'name'): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($nameColumn) {
            $table->string('slug')->nullable()->after($nameColumn);
        });

        DB::table($tableName)
            ->orderBy('id')
            ->each(function (object $record) use ($tableName, $nameColumn): void {
                $base = Str::slug((string) $record->{$nameColumn});
                if ($base === '') {
                    $base = Str::singular($tableName);
                }

                $slug = $base;
                $suffix = 2;

                while (
                    DB::table($tableName)
                        ->where('slug', $slug)
                        ->where('id', '!=', $record->id)
                        ->exists()
                ) {
                    $slug = $base.'-'.$suffix;
                    $suffix++;
                }

                DB::table($tableName)
                    ->where('id', $record->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table($tableName, function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }
};
