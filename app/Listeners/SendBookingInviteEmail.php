<?php

namespace App\Listeners;

use App\Events\BookingInvite;
use App\Mail\BookingInviteMail;
use Illuminate\Support\Facades\Mail;

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

        Mail::to($event->attendee->email)
            ->send(new BookingInviteMail($event->booking, $event->attendee));
    }
}
