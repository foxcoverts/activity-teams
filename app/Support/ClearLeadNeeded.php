<?php

namespace App\Support;

use App\Models\Booking;

final class ClearLeadNeeded
{
    public function __construct(private ResolveLeadNeededInbox $resolveInbox) {}

    public function __invoke(Booking $booking): void
    {
        if ($booking->lead_needed_at === null) {
            return;
        }

        $booking->forceFill(['lead_needed_at' => null])->save();

        ($this->resolveInbox)($booking);
        ManagerAttention::flush($booking->team_id);
    }
}
