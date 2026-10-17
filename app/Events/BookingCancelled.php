<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

class BookingCancelled
{
    use SerializesModels;

    public function __construct(
        public Booking $booking,
        public User $actor,
        public ?string $reason = null
    ) {}
}
