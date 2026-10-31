<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('audiences', 'sort')) {
            Schema::table('audiences', function (Blueprint $table) {
                $table->dropColumn('sort');
            });
        }

        foreach (DB::table('audiences')->where('name', 'Unspecified')->orderBy('id')->get() as $audience) {
            $activityName = DB::table('activity_audiences')
                ->join('activities', 'activities.id', '=', 'activity_audiences.activity_id')
                ->where('activity_audiences.audience_id', $audience->id)
                ->orderBy('activities.name')
                ->value('activities.name');

            if (blank($activityName)) {
                continue;
            }

            DB::table('audiences')
                ->where('id', $audience->id)
                ->update([
                    'name' => 'Unspecified — '.$activityName,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('audiences')->where('name', 'like', 'Unspecified — %')->orderBy('id')->get() as $audience) {
            $activityName = DB::table('activity_audiences')
                ->join('activities', 'activities.id', '=', 'activity_audiences.activity_id')
                ->where('activity_audiences.audience_id', $audience->id)
                ->orderBy('activities.name')
                ->value('activities.name');

            if ($activityName === null || $audience->name !== 'Unspecified — '.$activityName) {
                continue;
            }

            DB::table('audiences')
                ->where('id', $audience->id)
                ->update([
                    'name' => 'Unspecified',
                    'updated_at' => now(),
                ]);
        }

        if (! Schema::hasColumn('audiences', 'sort')) {
            Schema::table('audiences', function (Blueprint $table) {
                $table->unsignedInteger('sort')->default(0);
            });
        }
    }
};
