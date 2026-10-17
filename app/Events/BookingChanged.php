<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

class BookingChanged
{
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $changes
     * @param  array{start_at: mixed, end_at: mixed, timezone: mixed}|null  $previousSchedule
     */
    public function __construct(
        public Booking $booking,
        public User $actor,
        public array $changes = [],
        public ?array $previousSchedule = null,
    ) {}
}
