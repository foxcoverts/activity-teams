<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Support\BookingNotificationWindow;

class SendBookingCancelledEmail
{
    public function handle(BookingCancelled $event): void
    {
        BookingNotificationWindow::push(
            $event->booking,
            BookingNotificationWindow::CANCELLED,
            $event->reason,
        );
    }
}
