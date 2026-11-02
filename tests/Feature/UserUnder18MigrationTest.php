<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserUnder18MigrationTest extends TestCase
{
    public function test_legacy_sections_backfill_under_18_before_the_section_column_is_removed(): void
    {
        $originalConnection = config('database.default');
        $connectionName = 'under_18_migration_test';
        config([
            "database.connections.{$connectionName}" => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge($connectionName);
        DB::setDefaultConnection($connectionName);

        try {
            Schema::create('users', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('section');
            });

            $expected = [
                'squirrel' => true,
                'beaver' => true,
                'cub' => true,
                'scout' => true,
                'explorer' => true,
                'network' => false,
                'adult' => false,
                'parent' => false,
            ];

            foreach (array_keys($expected) as $section) {
                DB::table('users')->insert(['id' => $section, 'section' => $section]);
            }

            $migration = require base_path('database/migrations/2026_11_02_214914_replace_user_section_with_under_18.php');
            $migration->up();

            foreach ($expected as $section => $under18) {
                $this->assertSame(
                    $under18,
                    (bool) DB::table('users')->where('id', $section)->value('under_18'),
                );
            }

            $this->assertFalse(Schema::hasColumn('users', 'section'));
            $this->assertTrue(Schema::hasColumn('users', 'under_18'));
        } finally {
            DB::purge($connectionName);
            DB::setDefaultConnection($originalConnection);
        }
    }
}
