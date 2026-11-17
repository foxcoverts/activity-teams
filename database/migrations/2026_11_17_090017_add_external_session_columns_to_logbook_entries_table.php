<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });

        Schema::table('logbook_entries', function (Blueprint $table): void {
            $table->foreignUlid('booking_id')->nullable()->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();

            $table->date('occurred_on')->nullable()->after('booking_id');
            $table->time('start_time')->nullable()->after('occurred_on');
            $table->string('timezone')->nullable()->after('start_time');
            $table->string('activity_label')->nullable()->after('timezone');
            $table->string('venue_name')->nullable()->after('activity_label');
            $table->string('role')->nullable()->after('venue_name');
        });

        $entries = DB::table('logbook_entries')
            ->join('bookings', 'bookings.id', '=', 'logbook_entries.booking_id')
            ->leftJoin('activities', 'activities.id', '=', 'bookings.activity_id')
            ->leftJoin('venues', 'venues.id', '=', 'bookings.venue_id')
            ->select([
                'logbook_entries.id',
                'bookings.start_at',
                'bookings.timezone',
                'bookings.activity_label as booking_activity_label',
                'activities.name as activity_name',
                'venues.name as venue_name',
            ])
            ->get();

        foreach ($entries as $entry) {
            $timezone = $entry->timezone ?: config('app.timezone');
            $start = $entry->start_at !== null
                ? Carbon::parse($entry->start_at)->timezone($timezone)
                : null;

            $activityLabel = filled($entry->booking_activity_label)
                ? $entry->booking_activity_label
                : $entry->activity_name;

            DB::table('logbook_entries')
                ->where('id', $entry->id)
                ->update([
                    'occurred_on' => $start?->toDateString(),
                    'start_time' => $start?->format('H:i:s'),
                    'timezone' => $timezone,
                    'activity_label' => $activityLabel,
                    'venue_name' => $entry->venue_name,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('logbook_entries')->whereNull('booking_id')->exists()) {
            throw new RuntimeException('External or orphaned logbook entries must be removed before this migration can be rolled back.');
        }

        Schema::table('logbook_entries', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
            $table->dropColumn([
                'occurred_on',
                'start_time',
                'timezone',
                'activity_label',
                'venue_name',
                'role',
            ]);
        });

        Schema::table('logbook_entries', function (Blueprint $table): void {
            $table->foreignUlid('booking_id')->nullable(false)->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });
    }
};
