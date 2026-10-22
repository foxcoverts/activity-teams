<?php

namespace App\Support;

use App\Models\Booking;

class BookingWhen
{
    public static function format(Booking $booking): string
    {
        $start = localDate($booking->start_at, $booking->timezone);
        $end = localDate($booking->end_at, $booking->timezone);
        $duration = $booking->start_at
            ->diffAsCarbonInterval($booking->end_at)
            ->locale(config('app.locale'))
            ->forHumans();

        if ($start->isSameDay($end)) {
            return __(':date, :start_time to :end_time (:duration)', [
                'date' => $start->isoFormat('ddd D MMM YYYY'),
                'start_time' => $start->isoFormat('HH:mm'),
                'end_time' => $end->isoFormat('HH:mm'),
                'duration' => $duration,
            ]);
        }

        return __(':start to :end (:duration)', [
            'start' => $start->isoFormat('ddd D MMM YYYY HH:mm'),
            'end' => $end->isoFormat('ddd D MMM YYYY HH:mm'),
            'duration' => $duration,
        ]);
    }
}
