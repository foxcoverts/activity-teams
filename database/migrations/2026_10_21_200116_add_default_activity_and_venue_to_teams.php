<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignUlid('default_activity_id')
                ->nullable()
                ->constrained('activities')
                ->nullOnDelete();
            $table->foreignUlid('default_venue_id')
                ->nullable()
                ->constrained('venues')
                ->nullOnDelete();
        });

        DB::table('teams')
            ->orderBy('id')
            ->each(function (object $team): void {
                $defaultActivityId = DB::table('activity_team')
                    ->join('activities', 'activities.id', '=', 'activity_team.activity_id')
                    ->where('activity_team.team_id', $team->id)
                    ->orderBy('activities.name')
                    ->orderBy('activities.id')
                    ->value('activity_team.activity_id');

                $defaultVenueId = DB::table('team_venue')
                    ->join('venues', 'venues.id', '=', 'team_venue.venue_id')
                    ->where('team_venue.team_id', $team->id)
                    ->orderBy('venues.name')
                    ->orderBy('venues.id')
                    ->value('team_venue.venue_id');

                DB::table('teams')
                    ->where('id', $team->id)
                    ->update([
                        'default_activity_id' => $defaultActivityId,
                        'default_venue_id' => $defaultVenueId,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_activity_id');
            $table->dropConstrainedForeignId('default_venue_id');
        });
    }
};
