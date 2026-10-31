<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('key_rings')) {
            return;
        }

        Schema::table('key_requirements', function (Blueprint $table): void {
            $table->dropForeign(['key_id']);
            $table->dropUnique(['key_id', 'activity_id', 'venue_id']);
        });

        Schema::table('key_covers', function (Blueprint $table): void {
            $table->dropForeign(['key_id']);
            $table->dropForeign(['covered_key_id']);
        });

        Schema::table('key_ring_key', function (Blueprint $table): void {
            $table->dropForeign(['key_ring_id']);
            $table->dropForeign(['key_id']);
        });

        Schema::rename('keys', 'locks');

        Schema::table('key_requirements', function (Blueprint $table): void {
            $table->renameColumn('key_id', 'lock_id');
        });

        Schema::rename('key_requirements', 'lock_requirements');

        Schema::table('lock_requirements', function (Blueprint $table): void {
            $table->foreign('lock_id')->references('id')->on('locks')->cascadeOnDelete();
            $table->unique(['lock_id', 'activity_id', 'venue_id']);
        });

        Schema::table('key_covers', function (Blueprint $table): void {
            $table->renameColumn('key_id', 'lock_id');
            $table->renameColumn('covered_key_id', 'covered_lock_id');
        });

        Schema::rename('key_covers', 'lock_covers');

        Schema::table('lock_covers', function (Blueprint $table): void {
            $table->foreign('lock_id')->references('id')->on('locks')->cascadeOnDelete();
            $table->foreign('covered_lock_id')->references('id')->on('locks')->cascadeOnDelete();
        });

        Schema::rename('key_rings', 'keys');

        Schema::table('key_ring_key', function (Blueprint $table): void {
            $table->renameColumn('key_id', 'lock_id');
            $table->renameColumn('key_ring_id', 'key_id');
        });

        Schema::rename('key_ring_key', 'key_lock');

        Schema::table('key_lock', function (Blueprint $table): void {
            $table->foreign('key_id')->references('id')->on('keys')->cascadeOnDelete();
            $table->foreign('lock_id')->references('id')->on('locks')->cascadeOnDelete();
        });

        $this->renameMorphs([
            'App\\Models\\Key' => 'App\\Models\\Lock',
            'App\\Models\\KeyRequirement' => 'App\\Models\\LockRequirement',
            'App\\Models\\KeyRing' => 'App\\Models\\Key',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('locks') || ! Schema::hasTable('key_lock')) {
            return;
        }

        Schema::table('key_lock', function (Blueprint $table): void {
            $table->dropForeign(['key_id']);
            $table->dropForeign(['lock_id']);
        });

        Schema::table('lock_covers', function (Blueprint $table): void {
            $table->dropForeign(['lock_id']);
            $table->dropForeign(['covered_lock_id']);
        });

        Schema::table('lock_requirements', function (Blueprint $table): void {
            $table->dropForeign(['lock_id']);
            $table->dropUnique(['lock_id', 'activity_id', 'venue_id']);
        });

        Schema::rename('key_lock', 'key_ring_key');

        Schema::table('key_ring_key', function (Blueprint $table): void {
            $table->renameColumn('key_id', 'key_ring_id');
            $table->renameColumn('lock_id', 'key_id');
        });

        Schema::rename('keys', 'key_rings');

        Schema::table('lock_covers', function (Blueprint $table): void {
            $table->renameColumn('covered_lock_id', 'covered_key_id');
            $table->renameColumn('lock_id', 'key_id');
        });

        Schema::rename('lock_covers', 'key_covers');

        Schema::rename('lock_requirements', 'key_requirements');

        Schema::table('key_requirements', function (Blueprint $table): void {
            $table->renameColumn('lock_id', 'key_id');
        });

        Schema::rename('locks', 'keys');

        Schema::table('key_requirements', function (Blueprint $table): void {
            $table->foreign('key_id')->references('id')->on('keys')->cascadeOnDelete();
            $table->unique(['key_id', 'activity_id', 'venue_id']);
        });

        Schema::table('key_covers', function (Blueprint $table): void {
            $table->foreign('key_id')->references('id')->on('keys')->cascadeOnDelete();
            $table->foreign('covered_key_id')->references('id')->on('keys')->cascadeOnDelete();
        });

        Schema::table('key_ring_key', function (Blueprint $table): void {
            $table->foreign('key_ring_id')->references('id')->on('key_rings')->cascadeOnDelete();
            $table->foreign('key_id')->references('id')->on('keys')->cascadeOnDelete();
        });

        $this->renameMorphs([
            'App\\Models\\Key' => 'App\\Models\\KeyRing',
            'App\\Models\\LockRequirement' => 'App\\Models\\KeyRequirement',
            'App\\Models\\Lock' => 'App\\Models\\Key',
        ]);
    }

    /**
     * @param  array<string, string>  $types
     */
    private function renameMorphs(array $types): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        foreach ($types as $from => $to) {
            DB::table('activity_log')->where('subject_type', $from)->update(['subject_type' => $to]);
        }
    }
};
