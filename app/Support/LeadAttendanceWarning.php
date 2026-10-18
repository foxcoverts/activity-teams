<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;

class LeadAttendanceWarning
{
    public static function message(Booking $booking, ?BookingAttendeeStatus $next = null): string
    {
        $message = __('You are the lead instructor.');

        if ($booking->isConfirmed() && $next !== BookingAttendeeStatus::Accepted) {
            $message .= ' '.__('If you cannot attend, another lead instructor will need to be found.');
        }

        return $message;
    }
}
