<?php

namespace App\Listeners;

use App\Events\BookingChanged;
use App\Events\BookingRestored;
use App\Models\User;
use App\Support\BookingNotificationWindow;

class SendBookingChangedEmail
{
    public function handle(BookingChanged|BookingRestored $event): void
    {
        $recipientUserIds = null;

        if ($event instanceof BookingRestored) {
            $event->booking->loadMissing('attendees');
            $recipientUserIds = $event->booking->attendees
                ->filter(fn (User $attendee): bool => $attendee->attendance?->needsAction() ?? false)
                ->map(fn (User $attendee): string => (string) $attendee->getKey())
                ->values()
                ->all();
        }

        BookingNotificationWindow::push(
            $event->booking,
            BookingNotificationWindow::UPDATED,
            changes: array_keys($event->changes),
            previousSchedule: $event->previousSchedule,
            recipientUserIds: $recipientUserIds,
        );
    }
}
