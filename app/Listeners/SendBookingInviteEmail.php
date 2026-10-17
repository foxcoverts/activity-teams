<?php

namespace App\Listeners;

use App\Events\BookingInvite;
use App\Support\BookingNotificationWindow;

class SendBookingInviteEmail
{
    public function handle(BookingInvite $event): void
    {
        if ($event->attendee->isSuspended()) {
            return;
        }
        if ($event->booking->isCancelled() || $event->booking->isPast()) {
            return;
        }

        BookingNotificationWindow::push(
            $event->booking,
            BookingNotificationWindow::INVITE,
            inviteUserId: $event->attendee->id,
        );
    }
}
