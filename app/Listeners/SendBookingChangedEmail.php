<?php

namespace App\Listeners;

use App\Events\BookingChanged;
use App\Events\BookingRestored;
use App\Mail\BookingStatusMail;
use Illuminate\Support\Facades\Mail;

class SendBookingChangedEmail
{
    public function handle(BookingChanged|BookingRestored $event): void
    {
        foreach ($event->booking->attendees as $attendee) {
            if ($attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
                continue;
            }

            Mail::to($attendee->email)->send(new BookingStatusMail(
                $event->booking,
                $attendee,
                subject: $event instanceof BookingRestored ? __('Booking restored') : __('Booking updated'),
                intro: $event instanceof BookingRestored
                    ? __('A cancelled booking has been restored.')
                    : __('A booking you are invited to has changed.'),
            ));
        }
    }
}
