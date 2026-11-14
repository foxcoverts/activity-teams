<?php

use App\Enums\BookingAttendeeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $accepted = BookingAttendeeStatus::Accepted->value;
        $appTimezone = (string) config('app.timezone');

        DB::table('booking_user')
            ->join('bookings', 'bookings.id', '=', 'booking_user.booking_id')
            ->where('booking_user.status', $accepted)
            ->whereNotNull('bookings.end_at')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('logbook_entries')
                    ->whereColumn('logbook_entries.user_id', 'booking_user.user_id')
                    ->whereColumn('logbook_entries.booking_id', 'booking_user.booking_id');
            })
            ->select([
                'booking_user.user_id',
                'booking_user.booking_id',
                'bookings.end_at',
                'bookings.timezone',
            ])
            ->orderBy('booking_user.booking_id')
            ->orderBy('booking_user.user_id')
            ->cursor()
            ->each(function (object $row) use ($appTimezone): void {
                $timezone = filled($row->timezone) ? (string) $row->timezone : $appTimezone;
                $sessionDay = Carbon::parse($row->end_at)
                    ->timezone($timezone)
                    ->startOfDay();

                if ($sessionDay->gt(now($timezone)->startOfDay())) {
                    return;
                }

                $now = now();

                DB::table('logbook_entries')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'user_id' => $row->user_id,
                    'booking_id' => $row->booking_id,
                    'hours' => null,
                    'participant_count' => null,
                    'weather' => null,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        // Empty historical backfill rows are indistinguishable from later empty saves.
    }
};
