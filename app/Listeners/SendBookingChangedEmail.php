<?php

namespace App\Listeners;

use App\Events\BookingChanged;
use App\Events\BookingRestored;
use App\Support\BookingNotificationWindow;

class SendBookingChangedEmail
{
    public function handle(BookingChanged|BookingRestored $event): void
    {
        BookingNotificationWindow::push(
            $event->booking,
            BookingNotificationWindow::UPDATED,
            changes: array_keys($event->changes),
            previousSchedule: $event->previousSchedule,
        );
    }
}
