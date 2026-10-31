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
        Schema::create('audiences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_audiences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('audience_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['activity_id', 'audience_id']);
        });

        Schema::table('activity_qualification_requirements', function (Blueprint $table) {
            $table->foreignUlid('activity_audience_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignUlid('audience_id')->nullable()->after('activity_id')->constrained()->restrictOnDelete();
        });

        Schema::table('activity_team', function (Blueprint $table) {
            $table->foreignUlid('default_audience_id')->nullable()->after('team_id')->constrained('audiences')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('group_name')->nullable()->change();
        });

        $this->backfill();

        Schema::table('activity_qualification_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
        });

        $this->requireAudienceColumns();
    }

    public function down(): void
    {
        Schema::table('activity_qualification_requirements', function (Blueprint $table) {
            $table->foreignUlid('activity_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $pairings = DB::table('activity_audiences')->pluck('activity_id', 'id');

        foreach ($pairings as $pairingId => $activityId) {
            DB::table('activity_qualification_requirements')
                ->where('activity_audience_id', $pairingId)
                ->update(['activity_id' => $activityId]);
        }

        Schema::table('activity_qualification_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_audience_id');
        });

        $this->requireColumn('activity_qualification_requirements', 'activity_id');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audience_id');
        });

        DB::table('bookings')->whereNull('group_name')->update(['group_name' => '']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('group_name')->nullable(false)->change();
        });

        Schema::table('activity_team', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_audience_id');
        });

        Schema::dropIfExists('activity_audiences');
        Schema::dropIfExists('audiences');
    }

    private function backfill(): void
    {
        $activityIds = DB::table('activity_qualification_requirements')
            ->distinct()
            ->pluck('activity_id')
            ->merge(DB::table('bookings')->distinct()->pluck('activity_id'))
            ->filter()
            ->unique()
            ->values();

        foreach ($activityIds as $activityId) {
            $now = now();
            $audienceId = (string) Str::ulid();
            $pairingId = (string) Str::ulid();

            $activityName = DB::table('activities')->where('id', $activityId)->value('name');

            DB::table('audiences')->insert([
                'id' => $audienceId,
                'name' => filled($activityName) ? 'Unspecified — '.$activityName : 'Unspecified',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('activity_audiences')->insert([
                'id' => $pairingId,
                'activity_id' => $activityId,
                'audience_id' => $audienceId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('activity_qualification_requirements')
                ->where('activity_id', $activityId)
                ->update(['activity_audience_id' => $pairingId]);

            DB::table('bookings')
                ->where('activity_id', $activityId)
                ->update(['audience_id' => $audienceId]);
        }
    }

    private function requireAudienceColumns(): void
    {
        Schema::table('activity_qualification_requirements', function (Blueprint $table) {
            $table->dropForeign(['activity_audience_id']);
        });

        Schema::table('activity_qualification_requirements', function (Blueprint $table) {
            $table->ulid('activity_audience_id')->nullable(false)->change();
            $table->foreign('activity_audience_id')->references('id')->on('activity_audiences')->cascadeOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['audience_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->ulid('audience_id')->nullable(false)->change();
            $table->foreign('audience_id')->references('id')->on('audiences')->restrictOnDelete();
        });
    }

    private function requireColumn(string $table, string $column): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->dropForeign([$column]);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->ulid($column)->nullable(false)->change();
            $blueprint->foreign($column)->references('id')->on('activities')->cascadeOnDelete();
        });
    }
};
