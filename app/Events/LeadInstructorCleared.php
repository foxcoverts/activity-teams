<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

class LeadInstructorCleared
{
    use SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?User $previousLead,
        public ?User $actor = null,
        public bool $attendeesWillBeUpdated = false,
    ) {}
}
