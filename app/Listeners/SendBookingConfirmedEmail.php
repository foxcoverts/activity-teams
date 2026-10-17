<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Support\BookingNotificationWindow;

class SendBookingConfirmedEmail
{
    public function handle(BookingConfirmed $event): void
    {
        BookingNotificationWindow::push($event->booking, BookingNotificationWindow::CONFIRMED);
    }
}
