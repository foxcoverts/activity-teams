<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_user', function (Blueprint $table) {
            $table->json('declined_sessions')->nullable()->after('session_reminded_at');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::table('booking_user')
                ->where('status', 'declined')
                ->orderBy('booking_id')
                ->orderBy('user_id')
                ->each(function (object $row): void {
                    $booking = DB::table('bookings')->where('id', $row->booking_id)->first();
                    if ($booking === null) {
                        return;
                    }

                    DB::table('booking_user')
                        ->where('booking_id', $row->booking_id)
                        ->where('user_id', $row->user_id)
                        ->update([
                            'declined_sessions' => json_encode([[
                                'start_at' => $booking->start_at,
                                'end_at' => $booking->end_at,
                                'venue_id' => $booking->venue_id,
                            ]], JSON_THROW_ON_ERROR),
                        ]);
                });

            return;
        }

        DB::statement(<<<'SQL'
            UPDATE booking_user
            INNER JOIN bookings ON bookings.id = booking_user.booking_id
            SET booking_user.declined_sessions = JSON_ARRAY(
                JSON_OBJECT(
                    'start_at', bookings.start_at,
                    'end_at', bookings.end_at,
                    'venue_id', bookings.venue_id
                )
            )
            WHERE booking_user.status = 'declined'
        SQL);
    }

    public function down(): void
    {
        Schema::table('booking_user', function (Blueprint $table) {
            $table->dropColumn('declined_sessions');
        });
    }
};
