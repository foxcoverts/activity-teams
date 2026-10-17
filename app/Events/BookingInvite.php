<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

class BookingInvite
{
    use SerializesModels;

    public function __construct(
        public Booking $booking,
        public User $attendee
    ) {}
}
