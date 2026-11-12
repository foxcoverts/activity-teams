<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\InboxNotification;
use App\Notifications\LeadNeededNotification;

final class ResolveLeadNeededInbox
{
    public function __invoke(Booking $booking): void
    {
        InboxNotification::query()
            ->whereNull('read_at')
            ->where('type', LeadNeededNotification::class)
            ->where('data->subject_type', Booking::class)
            ->where('data->subject_id', $booking->getKey())
            ->orderBy('id')
            ->each(fn (InboxNotification $notification) => $notification->markReadForDelivery());
    }
}
