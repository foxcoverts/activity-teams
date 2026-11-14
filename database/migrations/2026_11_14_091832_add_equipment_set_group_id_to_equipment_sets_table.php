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
        Schema::table('equipment_sets', function (Blueprint $table): void {
            $table->foreignUlid('equipment_set_group_id')
                ->nullable()
                ->after('team_id')
                ->constrained('equipment_set_groups')
                ->restrictOnDelete();
        });

        $teamIds = DB::table('equipment_sets')->distinct()->pluck('team_id');

        $debriefTeamIds = DB::table('teams')
            ->where(function ($query): void {
                $query
                    ->where('features->debrief', true)
                    ->orWhere('features->debrief->enabled', true);
            })
            ->pluck('id');

        foreach ($teamIds->merge($debriefTeamIds)->unique()->filter() as $teamId) {
            $groupId = DB::table('equipment_set_groups')
                ->where('team_id', $teamId)
                ->orderBy('rank')
                ->value('id');

            if ($groupId === null) {
                $groupId = (string) Str::ulid();
                $now = now();

                DB::table('equipment_set_groups')->insert([
                    'id' => $groupId,
                    'team_id' => $teamId,
                    'label' => 'Equipment',
                    'rank' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('equipment_sets')
                ->where('team_id', $teamId)
                ->whereNull('equipment_set_group_id')
                ->update(['equipment_set_group_id' => $groupId]);
        }

        Schema::table('equipment_sets', function (Blueprint $table): void {
            $table->ulid('equipment_set_group_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_sets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('equipment_set_group_id');
        });
    }
};
