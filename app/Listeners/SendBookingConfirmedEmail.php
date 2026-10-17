<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Mail\BookingStatusMail;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmedEmail
{
    public function handle(BookingConfirmed $event): void
    {
        foreach ($event->booking->attendees as $attendee) {
            if ($attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
                continue;
            }

            Mail::to($attendee->email)->send(new BookingStatusMail(
                $event->booking,
                $attendee,
                subject: __('Booking confirmed'),
                intro: __('A booking has been confirmed.'),
            ));
        }
    }
}
